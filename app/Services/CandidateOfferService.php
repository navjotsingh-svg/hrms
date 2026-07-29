<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateStageLog;
use App\Models\HiringOffer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CandidateOfferService
{
    public function __construct(
        private OfferVerificationService $offerVerificationService,
        private HiringOfferPdfService $offerPdfService,
    ) {}

    public function resolveByToken(string $token): HiringOffer
    {
        $offer = HiringOffer::query()
            ->with(['candidate', 'job.department', 'company', 'template'])
            ->where('access_token', $token)
            ->first();

        if (! $offer) {
            throw new NotFoundHttpException('Offer link is invalid or has expired.');
        }

        if ($offer->token_expires_at && $offer->token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This offer link has expired. Contact HR for a new link.'],
            ]);
        }

        return $offer;
    }

    public function reviewUrl(HiringOffer $offer): string
    {
        return route('offer.review', ['token' => $offer->access_token]);
    }

    public function generateAccessToken(HiringOffer $offer): string
    {
        $token = Str::random(64);
        $expiryDays = (int) config('hiring.offer_link.expires_days', 30);

        $offer->forceFill([
            'access_token' => $token,
            'token_expires_at' => now()->addDays($expiryDays),
        ])->save();

        return $token;
    }

    public function storePdf(HiringOffer $offer, string $pdfBinary): string
    {
        $path = "offers/{$offer->company_id}/{$offer->id}.pdf";
        Storage::disk('public')->put($path, $pdfBinary);

        $offer->update(['pdf_path' => $path]);

        return $path;
    }

    public function requestOtp(string $token): void
    {
        $offer = $this->resolveByToken($token);
        $this->assertCanRespond($offer);

        $this->offerVerificationService->sendOtp($offer);
    }

    public function verifyOtp(string $token, string $code): void
    {
        $offer = $this->resolveByToken($token);
        $this->assertCanRespond($offer);

        $this->offerVerificationService->verifyOtp($offer, $code);
    }

    public function accept(string $token, array $data, ?UploadedFile $signatureFile = null): HiringOffer
    {
        $offer = $this->resolveByToken($token);
        $this->assertCanRespond($offer);
        $this->offerVerificationService->assertRecentlyVerified($offer->fresh());

        $signatureName = trim((string) ($data['signature_name'] ?? ''));
        if ($signatureName === '') {
            throw ValidationException::withMessages([
                'signature_name' => ['Please enter your full name.'],
            ]);
        }

        return DB::transaction(function () use ($offer, $data, $signatureFile, $signatureName) {
            $signaturePath = null;

            if ($signatureFile) {
                $signaturePath = $this->storeSignatureFile($offer, $signatureFile);
            } elseif (! empty($data['signature_data_url'])) {
                $signaturePath = $this->storeSignatureFromDataUrl($offer, $data['signature_data_url']);
            }

            if (! $signaturePath) {
                throw ValidationException::withMessages([
                    'signature' => ['Please draw or upload your signature.'],
                ]);
            }

            $signedPdfPath = $this->generateSignedPdf($offer, $signaturePath, $signatureName);

            $offer->update([
                'status' => HiringOffer::STATUS_ACCEPTED,
                'responded_at' => now(),
                'signed_at' => now(),
                'signature_name' => $signatureName,
                'signature_image_path' => $signaturePath,
                'signed_pdf_path' => $signedPdfPath,
                'signature_ip' => request()?->ip(),
                'signature_meta' => [
                    'user_agent' => request()?->userAgent(),
                    'verified_email' => $offer->candidate?->email,
                    'otp_verified_at' => $offer->otp_verified_at?->toIso8601String(),
                ],
            ]);

            $this->markCandidateHired($offer);

            return $offer->fresh(['candidate', 'job', 'company']);
        });
    }

    public function decline(string $token, ?string $reason = null): HiringOffer
    {
        $offer = $this->resolveByToken($token);
        $this->assertCanRespond($offer);

        $offer->update([
            'status' => HiringOffer::STATUS_DECLINED,
            'responded_at' => now(),
            'decline_reason' => $reason ? trim($reason) : null,
        ]);

        $this->logCandidateDecline($offer, $reason);

        return $offer->fresh(['candidate', 'job', 'company']);
    }

    public function pdfContents(HiringOffer $offer): array
    {
        if ($offer->signed_pdf_path && Storage::disk('public')->exists($offer->signed_pdf_path)) {
            return [
                'binary' => Storage::disk('public')->get($offer->signed_pdf_path),
                'filename' => $this->offerPdfService->filename($offer),
            ];
        }

        if ($offer->pdf_path && Storage::disk('public')->exists($offer->pdf_path)) {
            return [
                'binary' => Storage::disk('public')->get($offer->pdf_path),
                'filename' => $this->offerPdfService->filename($offer),
            ];
        }

        $bodyHtml = trim((string) ($offer->letter_html ?? ''));
        if ($bodyHtml === '') {
            throw new NotFoundHttpException('Offer letter PDF is not available.');
        }

        return [
            'binary' => $this->offerPdfService->output($offer, $bodyHtml),
            'filename' => $this->offerPdfService->filename($offer),
        ];
    }

    private function assertCanRespond(HiringOffer $offer): void
    {
        if ($offer->status !== HiringOffer::STATUS_SENT) {
            throw ValidationException::withMessages([
                'status' => ['This offer is no longer available for response.'],
            ]);
        }
    }

    private function markCandidateHired(HiringOffer $offer): void
    {
        $candidate = $offer->candidate;
        if (! $candidate) {
            return;
        }

        $from = $candidate->stage;
        $candidate->update([
            'stage' => Candidate::STAGE_HIRED,
            'hired_at' => now(),
        ]);

        CandidateStageLog::query()->create([
            'candidate_id' => $candidate->id,
            'from_stage' => $from,
            'to_stage' => Candidate::STAGE_HIRED,
            'actor_user_id' => $offer->created_by_user_id,
            'notes' => 'Offer accepted and signed by candidate',
        ]);
    }

    private function logCandidateDecline(HiringOffer $offer, ?string $reason): void
    {
        $candidate = $offer->candidate;
        if (! $candidate) {
            return;
        }

        CandidateStageLog::query()->create([
            'candidate_id' => $candidate->id,
            'from_stage' => $candidate->stage,
            'to_stage' => $candidate->stage,
            'actor_user_id' => $offer->created_by_user_id,
            'notes' => 'Offer declined by candidate'.($reason ? ": {$reason}" : ''),
        ]);
    }

    private function storeSignatureFile(HiringOffer $offer, UploadedFile $file): string
    {
        $dir = public_path('images/offer-signatures/'.$offer->company_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'offer-'.$offer->id.'-'.time().'.png';
        $file->move($dir, $filename);

        return 'images/offer-signatures/'.$offer->company_id.'/'.$filename;
    }

    private function storeSignatureFromDataUrl(HiringOffer $offer, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $dataUrl)) {
            return null;
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl));
        if ($binary === false) {
            return null;
        }

        $dir = public_path('images/offer-signatures/'.$offer->company_id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'offer-'.$offer->id.'-'.time().'.png';
        file_put_contents($dir.'/'.$filename, $binary);

        return 'images/offer-signatures/'.$offer->company_id.'/'.$filename;
    }

    private function generateSignedPdf(HiringOffer $offer, string $signaturePath, string $signatureName): ?string
    {
        $bodyHtml = trim((string) ($offer->letter_html ?? ''));
        if ($bodyHtml === '') {
            return null;
        }

        $signatureFullPath = public_path(ltrim($signaturePath, '/'));
        $pdfBinary = $this->offerPdfService->outputWithSignature($offer, $bodyHtml, $signatureFullPath, $signatureName);
        $path = "offers/{$offer->company_id}/{$offer->id}-signed.pdf";
        Storage::disk('public')->put($path, $pdfBinary);

        return $path;
    }
}

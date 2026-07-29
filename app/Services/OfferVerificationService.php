<?php

namespace App\Services;

use App\Mail\OfferOtpMail;
use App\Models\HiringOffer;
use App\Models\HiringOfferVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OfferVerificationService
{
    public function sendOtp(HiringOffer $offer): void
    {
        $offer->loadMissing('candidate', 'company');

        $email = trim((string) ($offer->candidate?->email ?? ''));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => ['Candidate email is missing on this offer.'],
            ]);
        }

        $expiresMinutes = (int) config('hiring.offer_otp.expires_minutes', 10);
        $code = (string) random_int(100000, 999999);

        HiringOfferVerification::query()
            ->where('hiring_offer_id', $offer->id)
            ->whereNull('verified_at')
            ->delete();

        $offer->update(['otp_verified_at' => null]);

        HiringOfferVerification::query()->create([
            'hiring_offer_id' => $offer->id,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        $candidateName = trim(($offer->candidate?->first_name ?? '').' '.($offer->candidate?->last_name ?? '')) ?: 'Candidate';
        $companyName = $offer->company?->name ?? config('app.name', 'HRMS');

        Mail::to($email)->send(new OfferOtpMail(
            recipientName: $candidateName,
            companyName: $companyName,
            offerTitle: $offer->title,
            otpCode: $code,
            expiresMinutes: $expiresMinutes,
        ));
    }

    public function verifyOtp(HiringOffer $offer, string $code): HiringOfferVerification
    {
        $verification = HiringOfferVerification::query()
            ->where('hiring_offer_id', $offer->id)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'otp' => ['No verification code found. Request a new code.'],
            ]);
        }

        if ($verification->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['This verification code has expired. Request a new code.'],
            ]);
        }

        $maxAttempts = (int) config('hiring.offer_otp.max_attempts', 5);
        if ($verification->attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Request a new code.'],
            ]);
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        $verification->update(['verified_at' => now()]);
        $offer->update(['otp_verified_at' => now()]);

        return $verification->fresh();
    }

    public function assertRecentlyVerified(HiringOffer $offer): void
    {
        $windowMinutes = (int) config('hiring.offer_otp.accept_window_minutes', 15);

        if (! $offer->otp_verified_at || $offer->otp_verified_at->lt(now()->subMinutes($windowMinutes))) {
            throw ValidationException::withMessages([
                'otp' => ['Email verification is required before accepting the offer.'],
            ]);
        }
    }
}

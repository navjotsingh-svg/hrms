<?php

namespace App\Http\Controllers;

use App\Models\HiringOffer;
use App\Services\CandidateOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PublicOfferController extends Controller
{
    public function __construct(private CandidateOfferService $candidateOfferService) {}

    public function show(string $token): View
    {
        $offer = $this->candidateOfferService->resolveByToken($token);
        $offer->loadMissing(['candidate', 'company', 'job']);

        return view('offers.review', [
            'offer' => $offer,
            'token' => $token,
            'companyName' => $offer->company?->name ?? config('app.name'),
            'candidateName' => trim(($offer->candidate?->first_name ?? '').' '.($offer->candidate?->last_name ?? '')),
            'canRespond' => $offer->status === HiringOffer::STATUS_SENT,
            'isAccepted' => $offer->status === HiringOffer::STATUS_ACCEPTED,
            'isDeclined' => $offer->status === HiringOffer::STATUS_DECLINED,
        ]);
    }

    public function pdf(string $token): Response
    {
        $offer = $this->candidateOfferService->resolveByToken($token);
        $file = $this->candidateOfferService->pdfContents($offer);

        return response($file['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
        ]);
    }

    public function requestOtp(string $token): JsonResponse
    {
        $this->candidateOfferService->requestOtp($token);

        return response()->json([
            'success' => true,
            'message' => 'A verification code has been sent to your email.',
        ]);
    }

    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $this->candidateOfferService->verifyOtp($token, $validated['otp']);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now confirm acceptance.',
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => ['required', 'string', 'max:100'],
            'signature_data_url' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if (empty($validated['signature_data_url']) && ! $request->hasFile('signature_file')) {
            return response()->json([
                'success' => false,
                'message' => 'Please draw or upload your signature.',
                'errors' => ['signature' => ['Please draw or upload your signature.']],
            ], 422);
        }

        $offer = $this->candidateOfferService->accept(
            $token,
            $validated,
            $request->file('signature_file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your offer has been accepted and signed successfully.',
            'data' => [
                'status' => $offer->status,
            ],
        ]);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $offer = $this->candidateOfferService->decline($token, $validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'You have declined this offer.',
            'data' => [
                'status' => $offer->status,
            ],
        ]);
    }
}

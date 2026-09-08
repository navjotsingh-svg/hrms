<?php

namespace App\Services;

use App\Models\HiringOffer;
use Barryvdh\DomPDF\Facade\Pdf;

class HiringOfferPdfService
{
    public function output(HiringOffer $offer, string $bodyHtml): string
    {
        return $this->render($offer, $bodyHtml)->output();
    }

    public function outputWithSignature(HiringOffer $offer, string $bodyHtml, ?string $signaturePath, string $signatureName): string
    {
        return $this->render($offer, $bodyHtml, $signaturePath, $signatureName)->output();
    }

    public function render(HiringOffer $offer, string $bodyHtml, ?string $signaturePath = null, ?string $signatureName = null): \Barryvdh\DomPDF\PDF
    {
        $offer->loadMissing(['company', 'candidate', 'job']);
        $company = $offer->company;

        return Pdf::loadView('hiring.offer-letter-pdf', [
            'title' => $offer->title,
            'bodyHtml' => $bodyHtml,
            'company' => $company,
            'companyLegalName' => $company?->legal_name ?? $company?->name ?? config('app.name'),
            'logoPath' => $this->resolveLogoPath($company?->logo),
            'signaturePath' => $signaturePath && is_file($signaturePath) ? $signaturePath : null,
            'signatureName' => $signatureName,
            'signedAtLabel' => now()->format('d M Y'),
        ])->setPaper('a4', 'portrait');
    }

    public function filename(HiringOffer $offer): string
    {
        $offer->loadMissing('candidate');
        $slug = preg_replace('/[^\w\-]+/', '-', strtolower($offer->title ?: 'offer')) ?: 'offer';
        $candidate = preg_replace('/[^\w\-]+/', '-', strtolower(trim(($offer->candidate?->first_name ?? '').'-'.($offer->candidate?->last_name ?? '')))) ?: 'candidate';

        return "offer-letter-{$slug}-{$candidate}.pdf";
    }

    private function resolveLogoPath(?string $logo): ?string
    {
        if (! $logo) {
            return null;
        }

        $candidate = public_path(ltrim($logo, '/'));

        return is_file($candidate) ? $candidate : null;
    }
}

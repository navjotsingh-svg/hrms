<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $companyLegalName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 0;
            padding: 28px 32px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 1px solid #d0d0d0;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 52px; max-width: 160px; }
        .company-name {
            font-size: 17px;
            font-weight: bold;
            margin: 0 0 4px;
        }
        .company-meta { line-height: 1.5; color: #444; font-size: 10px; }
        .doc-title {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 18px;
        }
        .letter-body p { margin: 0 0 10px; }
        .letter-body ul, .letter-body ol { margin: 0 0 10px 18px; padding: 0; }
        .letter-body h1, .letter-body h2, .letter-body h3 {
            margin: 14px 0 8px;
            font-size: 13px;
        }
        .letter-body strong { font-weight: bold; }
        .letter-body em { font-style: italic; }
        .signature-block {
            margin-top: 36px;
            padding-top: 16px;
            border-top: 1px solid #d0d0d0;
        }
        .signature-label { font-size: 11px; color: #555; margin: 0 0 8px; }
        .signature-image { max-height: 72px; max-width: 220px; display: block; margin-bottom: 8px; }
        .signature-name { font-weight: bold; margin: 0 0 4px; }
        .signature-date { margin: 0; color: #555; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 65%;">
                    <p class="company-name">{{ $companyLegalName }}</p>
                    @if ($company)
                        <div class="company-meta">
                            @php
                                $address = collect([
                                    $company->address_line_1,
                                    $company->address_line_2,
                                    $company->city,
                                    $company->state,
                                    $company->postal_code,
                                    $company->country,
                                ])->filter()->implode(', ');
                            @endphp
                            @if ($address)
                                {{ $address }}<br>
                            @endif
                            @if ($company->phone)
                                {{ $company->phone }}<br>
                            @endif
                            @if ($company->email)
                                {{ $company->email }}
                            @endif
                        </div>
                    @endif
                </td>
                <td style="width: 35%; text-align: right;">
                    @if (!empty($logoPath))
                        <img src="{{ $logoPath }}" alt="" class="logo">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p class="doc-title">{{ $title }}</p>

    <div class="letter-body">
        {!! $bodyHtml !!}
    </div>

    @if (!empty($signaturePath))
        <div class="signature-block">
            <p class="signature-label">Accepted and signed by:</p>
            <img src="{{ $signaturePath }}" alt="Signature" class="signature-image">
            <p class="signature-name">{{ $signatureName }}</p>
            <p class="signature-date">Date: {{ $signedAtLabel ?? now()->format('d M Y') }}</p>
        </div>
    @endif
</body>
</html>

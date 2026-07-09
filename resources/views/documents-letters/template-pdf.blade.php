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
            margin: 0 0 6px;
        }
        .doc-subject {
            color: #555;
            margin: 0 0 18px;
            font-size: 11px;
        }
        .letter-body p { margin: 0 0 10px; }
        .letter-body ul, .letter-body ol { margin: 0 0 10px 18px; padding: 0; }
        .letter-body h1, .letter-body h2, .letter-body h3 {
            margin: 14px 0 8px;
            font-size: 13px;
        }
        .letter-body strong { font-weight: bold; }
        .letter-body em { font-style: italic; }
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
    @if (!empty($subject))
        <p class="doc-subject">Subject: {{ $subject }}</p>
    @endif

    <div class="letter-body">
        {!! $bodyHtml !!}
    </div>
</body>
</html>

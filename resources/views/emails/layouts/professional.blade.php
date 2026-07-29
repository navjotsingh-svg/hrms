<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailTitle ?? ($headline ?? config('mail.from.name', config('app.name', 'HRMS'))) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; -webkit-font-smoothing: antialiased;">
    @php
        $brandName = $brandName ?? config('mail.from.name', config('app.name', 'HRMS'));
        $headline = $headline ?? 'Notification';
        $subheadline = $subheadline ?? null;
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%); border-radius: 16px 16px 0 0; padding: 28px 36px; text-align: center;">
                            <span style="color: #ffffff; font-size: 14px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;">{{ $brandName }}</span>
                            <h1 style="margin: 12px 0 0; color: #ffffff; font-size: 24px; font-weight: 700; line-height: 1.35;">{{ $headline }}</h1>
                            @if ($subheadline)
                                <p style="margin: 10px 0 0; color: rgba(255,255,255,0.85); font-size: 15px; line-height: 1.5;">{{ $subheadline }}</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; padding: 36px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            @isset($purpose)
                                @include('emails.partials.purpose-box', ['purpose' => $purpose])
                            @endisset

                            @yield('email-body')
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 16px 16px; padding: 24px 36px 32px;">
                            @include('emails.partials.footer', ['brandName' => $brandName, 'supportNote' => $supportNote ?? null])
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

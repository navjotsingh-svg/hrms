<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%); border-radius: 16px 16px 0 0; padding: 28px 36px; text-align: center;">
                            <span style="color: #ffffff; font-size: 16px; font-weight: 700; letter-spacing: 0.08em;">{{ config('mail.from.name', config('app.name', 'HRMS')) }}</span>
                            <h1 style="margin: 12px 0 0; color: #ffffff; font-size: 22px; font-weight: 700; line-height: 1.3;">
                                Action required
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; padding: 36px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
                                Hello <strong>{{ $recipientName }}</strong>,
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #475569;">
                                {{ $intro }}
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 14px 20px; background: #eff6ff; border-bottom: 1px solid #dbeafe;">
                                        <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #1e3a5f;">Request details</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            @foreach ($details as $label => $value)
                                                <tr>
                                                    <td style="padding: 8px 0; width: 140px; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">{{ $label }}</td>
                                                    <td style="padding: 8px 0; font-size: 14px; color: #0f172a;">{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto;">
                                <tr>
                                    <td style="border-radius: 10px; background: linear-gradient(180deg, #1e3a5f 0%, #0f2744 100%);">
                                        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px;">
                                            {{ $actionLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 16px 16px; padding: 24px 36px 32px; text-align: center;">
                            <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                                &copy; {{ date('Y') }} {{ config('mail.from.name', config('app.name', 'HRMS')) }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

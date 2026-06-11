<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('mail.from.name', config('app.name', 'HRMS')) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%); border-radius: 16px 16px 0 0; padding: 32px 36px; text-align: center;">
                            <div style="display: inline-block; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 10px 18px; margin-bottom: 16px;">
                                <span style="color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: 0.08em;">{{ config('mail.from.name', config('app.name', 'HRMS')) }}</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.3;">
                                Welcome aboard
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.82); font-size: 15px; line-height: 1.5;">
                                Your company account is ready on our HRMS platform
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; padding: 36px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">
                                Hello <strong>{{ $company->contact_person_name ?? $company->name }}</strong>,
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #475569;">
                                <strong style="color: #0f172a;">{{ $company->name }}</strong> has been registered successfully.
                                You can now sign in as the company administrator to manage employees, attendance, leave, and payroll.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 14px 20px; background: #eff6ff; border-bottom: 1px solid #dbeafe;">
                                        <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #1e3a5f;">Your login credentials</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding: 8px 0; width: 130px; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">Login URL</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #0f172a;">
                                                    <a href="{{ url('/') }}" style="color: #1e3a5f; text-decoration: none; font-weight: 600;">{{ url('/') }}</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">Email</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $company->email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">Password</td>
                                                <td style="padding: 8px 0;">
                                                    <code style="display: inline-block; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 15px; font-weight: 700; letter-spacing: 0.04em; color: #1e3a5f; font-family: Consolas, 'Courier New', monospace;">{{ $plainPassword }}</code>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">Role</td>
                                                <td style="padding: 8px 0; font-size: 14px; color: #0f172a;">Company Administrator</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto 28px;">
                                <tr>
                                    <td style="border-radius: 10px; background: linear-gradient(180deg, #1e3a5f 0%, #0f2744 100%);">
                                        <a href="{{ url('/') }}" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px;">
                                            Sign in to your account
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;">
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 13px; line-height: 1.6; color: #92400e;">
                                        <strong>Security tip:</strong> Please change your password immediately after your first login. Do not share these credentials with anyone.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 16px 16px; padding: 24px 36px 32px; text-align: center;">
                            <p style="margin: 0 0 6px; font-size: 14px; color: #64748b; line-height: 1.6;">
                                Need help getting started? Reply to this email and our team will assist you.
                            </p>
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

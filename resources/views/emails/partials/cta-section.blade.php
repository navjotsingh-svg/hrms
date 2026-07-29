@if (! empty($actionUrl))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto 20px;">
        <tr>
            <td style="border-radius: 10px; background: linear-gradient(180deg, #1e3a5f 0%, #0f2744 100%);">
                <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px;">
                    {{ $actionLabel ?? 'Open in HRMS' }}
                </a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 24px; font-size: 12px; line-height: 1.6; color: #64748b; word-break: break-all; text-align: center;">
        If the button does not work, copy and paste this link into your browser:<br>
        <span style="color: #1e3a5f;">{{ $actionUrl }}</span>
    </p>
@endif

@php
    $detailsTitle = $detailsTitle ?? 'Complete details';
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px;">
    <tr>
        <td style="padding: 14px 20px; background: #eff6ff; border-bottom: 1px solid #dbeafe;">
            <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #1e3a5f;">{{ $detailsTitle }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding: 18px 20px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                @foreach ($details as $label => $value)
                    @if (filled($value))
                        <tr>
                            <td style="padding: 8px 0; width: 150px; font-size: 13px; font-weight: 600; color: #64748b; vertical-align: top;">{{ $label }}</td>
                            <td style="padding: 8px 0; font-size: 14px; color: #0f172a; line-height: 1.6; white-space: pre-wrap;">{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </td>
    </tr>
</table>

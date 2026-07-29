@if (! empty($nextSteps))
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 14px 20px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #475569;">What to do next</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 18px 20px;">
                <ol style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #334155;">
                    @foreach ($nextSteps as $step)
                        <li style="margin-bottom: 6px;">{{ $step }}</li>
                    @endforeach
                </ol>
            </td>
        </tr>
    </table>
@endif

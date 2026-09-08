@if (! empty($supportNote))
    <p style="margin: 0 0 10px; font-size: 14px; color: #64748b; line-height: 1.6; text-align: center;">{{ $supportNote }}</p>
@endif
<p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.6;">
    This is an automated message from {{ $brandName }}. Please do not reply unless instructed.<br>
    &copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.
</p>

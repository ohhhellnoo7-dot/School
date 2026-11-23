<svg class="student-payment-loading-art" aria-hidden="true" viewBox="0 0 420 215">
    <defs>
        <linearGradient id="payment-printer-top" x2="0" y2="1" gradientUnits="objectBoundingBox">
            <stop stop-color="var(--payment-art-light)"/><stop offset="1" stop-color="var(--payment-accent)"/>
        </linearGradient>
        <linearGradient id="payment-printer-front" x2="1" y2=".8">
            <stop stop-color="var(--payment-accent)"/><stop offset="1" stop-color="var(--payment-art-dark)"/>
        </linearGradient>
        <linearGradient id="payment-paper" x2="1" y2="1">
            <stop stop-color="var(--surface)"/><stop offset="1" stop-color="var(--payment-paper)"/>
        </linearGradient>
    </defs>
    <ellipse cx="210" cy="183" rx="145" ry="17" fill="var(--payment-ink)" opacity=".13"/>
    <g fill="var(--success, var(--payment-accent))" opacity=".75">
        <path d="M128 153C84 139 67 107 72 65c38 12 58 38 56 88Z"/><path d="M128 155C85 165 53 149 38 121c39-10 70 1 90 34Z"/>
        <path d="M297 157c-2-39 22-69 61-75-3 38-26 65-61 75Z"/><path d="M298 161c28-31 57-39 86-27-15 25-53 39-86 27Z"/>
    </g>
    <g fill="none" stroke="var(--surface)" opacity=".28" stroke-width="2"><path d="m75 73 47 71M46 124l69 28m238-64-48 59m68-9-60 19"/></g>
    <path d="m100 111 32-27q10-9 26-9h117q15 0 26 10l26 24v48q0 26-24 26H127q-27 0-27-24Z" fill="var(--payment-art-dark)"/>
    <path d="m100 111 32-27q10-9 26-9h117q15 0 26 10l26 24-29 24H126Z" fill="url(#payment-printer-top)"/>
    <path d="M146 95h132l16 14H132Z" fill="var(--payment-art-dark)"/>
    <path d="M155 107V48c0-16-8-26-22-26h104c20 0 32 13 32 36v49Z" fill="url(#payment-paper)" stroke="var(--payment-outline)" stroke-width="2"/>
    <path d="M133 22c-14 0-21 11-22 25h44c0-15-8-25-22-25Z" fill="var(--payment-art-light)"/>
    <text x="173" y="47" fill="var(--payment-ink)" font-size="11" font-weight="800" letter-spacing=".8">{{ __('INVOICE') }}</text>
    <path d="M174 55h69m-69 11h48m-28 14h48m-48 14h38" stroke="var(--payment-ink)" stroke-opacity=".56" stroke-width="4" stroke-linecap="round"/>
    <path d="m174 79 4 4 7-8m-11 18 4 4 7-8" fill="none" stroke="var(--success, var(--payment-accent))" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M100 111q10-9 26 6l15 15h145q11 0 21-9l20-14v48q0 21-22 21H125q-25 0-25-20Z" fill="url(#payment-printer-front)"/>
    <rect x="156" y="144" width="143" height="22" rx="10" fill="var(--payment-art-dark)"/>
    <rect x="175" y="151" width="104" height="6" rx="3" fill="var(--payment-art-light)"/>
    <g fill="var(--warning, var(--payment-accent))" stroke="var(--payment-ink)" stroke-opacity=".22">
        <path d="M80 151h49v33c0 12-49 12-49 0Z"/><ellipse cx="104.5" cy="151" rx="24.5" ry="8"/>
        <path d="M80 164c0 11 49 11 49 0m-49 10c0 11 49 11 49 0" fill="none"/>
        <path d="M53 174h46v17c0 11-46 11-46 0Z"/><ellipse cx="76" cy="174" rx="23" ry="7"/>
    </g>
    <g transform="translate(308 116) rotate(11)">
        <rect width="66" height="72" rx="12" y="4" fill="var(--payment-art-dark)" opacity=".35"/>
        <rect width="66" height="70" rx="12" fill="url(#payment-paper)" stroke="var(--payment-outline)" stroke-width="2"/>
        <path d="M0 12Q0 0 12 0h42q12 0 12 12v10H0Z" fill="var(--payment-accent)"/>
        <path d="M17-4V9m32-13V9" stroke="var(--payment-ink)" stroke-width="5" stroke-linecap="round"/>
        <path d="M12 34h8v8h-8Zm17 0h8v8h-8Zm17 0h8v8h-8ZM12 49h8v8h-8Zm17 0h8v8h-8Zm17 0h8v8h-8Z" fill="var(--payment-accent)" opacity=".65"/>
    </g>
    <path d="m287 41 6-11m2 21 13-5m-12 15 11 3" stroke="var(--warning, var(--payment-accent))" stroke-width="5" stroke-linecap="round"/>
</svg>

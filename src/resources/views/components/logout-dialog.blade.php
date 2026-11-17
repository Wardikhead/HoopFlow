@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', $user?->name ?? ''))
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->slice(0, 2)
        ->implode('');
@endphp

{{-- Shared confirmation dialog. Both logout forms are intercepted by
     resources/js/ui/logout.js and open this dialog instead of submitting. --}}
<dialog class="hf-dialog hf-logout"
        data-hf-logout-dialog
        aria-labelledby="hf-logout-title"
        aria-describedby="hf-logout-copy">
    <span class="hf-logout__accent" aria-hidden="true"></span>

    <div class="hf-logout__body">
        <span class="hf-logout__icon" aria-hidden="true">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        </span>

        <div class="hf-logout__copy">
            <p class="hf-logout__eyebrow">End session</p>
            <h2 class="hf-dialog__title" id="hf-logout-title">Log out?</h2>
            <p class="hf-logout__text" id="hf-logout-copy" data-hf-logout-copy>
                You'll be signed out of HoopFlow on this device. Your tournaments, rosters, and results stay safe.
            </p>

            @if ($user)
                <p class="hf-logout__account">
                    <span class="hf-avatar" aria-hidden="true">{{ $initials }}</span>
                    <span class="hf-logout__identity">
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $user->email }}</span>
                    </span>
                </p>
            @endif
        </div>
    </div>

    <div class="hf-logout__actions">
        <button type="button"
                class="hf-btn hf-btn--ghost"
                data-hf-logout-cancel
                data-testid="logout-cancel"
                autofocus>
            Stay signed in
        </button>
        <button type="button"
                class="hf-btn hf-btn--danger"
                data-hf-logout-confirm
                data-testid="logout-confirm">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span data-hf-logout-label>Log out</span>
        </button>
    </div>
</dialog>

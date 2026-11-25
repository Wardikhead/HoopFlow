{{-- Blade view for Password meter. --}}
@props(['model' => 'password'])

<div class="hf-password-meter" :class="'hf-score-'+evaluation.score" x-show="focused || {{ $model }}.length > 0" x-cloak>
    <div
        class="hf-password-bars"
        role="meter"
        aria-label="Password strength"
        aria-valuemin="0"
        aria-valuemax="4"
        :aria-valuenow="evaluation.score"
        :aria-valuetext="evaluation.label || 'Empty'">
        <template x-for="index in 4" :key="index">
            <span class="hf-password-bar" :class="{ 'is-filled': index <= evaluation.score }"></span>
        </template>
    </div>
    <p class="hf-password-hint" role="status" aria-live="polite" x-text="evaluation.hint"></p>
    <ul class="hf-password-checks" aria-label="Password requirements">
        @foreach ([
            'length' => 'At least 8 characters',
            'lowercase' => 'A lowercase letter',
            'uppercase' => 'An uppercase letter',
            'number' => 'A number',
            'symbol' => 'A symbol',
        ] as $key => $label)
            <li class="hf-password-check" :class="{ 'is-met': evaluation.checks['{{ $key }}'] }">
                <span class="hf-sr-only" x-text="evaluation.checks['{{ $key }}'] ? 'Met: ' : 'Not met: '"></span>
                <i class="bi" :class="evaluation.checks['{{ $key }}'] ? 'bi-check-circle-fill' : 'bi-circle'" aria-hidden="true"></i>
                <span>{{ $label }}</span>
            </li>
        @endforeach
    </ul>
</div>

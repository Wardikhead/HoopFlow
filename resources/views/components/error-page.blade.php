@props([
    'code',
    'title',
    'message',
    'detail' => null,
    'primaryLabel' => 'Go home',
    'primaryHref' => null,
    'secondaryLabel' => 'Go back',
    'secondaryHref' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ $title }} · HoopFlow</title>
    <link rel="icon" type="image/png" href="{{ asset('img/hoopflow icon without background.png') }}">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('hf-theme') || 'system';
                var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.documentElement.dataset.density = localStorage.getItem('hf-density') || 'comfortable';
                document.documentElement.dataset.motion = localStorage.getItem('hf-motion') || 'system';
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    @vite(['resources/css/app.scss'])
</head>
<body class="hf-error-page">
    <main class="hf-error-shell">
        <span class="hf-error-court" aria-hidden="true"></span>
        <div class="hf-error-brand">
            <x-brand size="md" />
        </div>
        <p class="hf-eyebrow">{{ $code }}</p>
        <h1 class="hf-error-title">{{ $title }}</h1>
        <p class="hf-error-message">{{ $message }}</p>
        @if ($detail)
            <p class="hf-error-detail">{{ $detail }}</p>
        @endif
        <div class="hf-btn-row hf-error-actions">
            <a class="hf-btn hf-btn--primary" href="{{ $primaryHref ?? route('home') }}">{{ $primaryLabel }}</a>
            <a class="hf-btn" href="{{ $secondaryHref ?? url()->previous() }}">{{ $secondaryLabel }}</a>
        </div>
    </main>
</body>
</html>

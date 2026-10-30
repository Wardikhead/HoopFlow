<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-light">
    <a class="visually-hidden-focusable" href="#main">Skip to content</a>

    <main id="main">
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>{{ config('app.name', 'NEXVARY CX Cloud') }}</title>
    @viteReactRefresh
    @vite(['resources/css/nexvary.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="nx-bg-base font-sans antialiased">
    @inertia
</body>
</html>

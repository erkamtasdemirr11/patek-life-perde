<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('kod') — @yield('baslik') | {{ config('firma.ad') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    {{-- Vite'a bağlı değil: derlenmiş dosyalar yüklenemese bile hata sayfası açılır. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #0a2a1d; color: #faf8f2; font-family: ui-sans-serif, system-ui, sans-serif; text-align: center; }
        .kutu { max-width: 520px; }
        .kod { margin: 0; font-size: 5rem; font-weight: 700; line-height: 1; color: #e5cf95; }
        h1 { margin: 16px 0 8px; font-size: 1.5rem; font-weight: 600; }
        p { margin: 0 0 24px; line-height: 1.6; color: rgba(250, 248, 242, .75); }
        a { display: inline-block; padding: 12px 22px; border-radius: 8px; background: #c49a44; color: #061a13; font-weight: 600; text-decoration: none; }
        a:hover { background: #d6b56d; }
    </style>
</head>
<body>
    <div class="kutu">
        <p class="kod">@yield('kod')</p>
        <h1>@yield('baslik')</h1>
        <p>@yield('mesaj')</p>
        <a href="{{ url('/') }}">Ana sayfaya dön</a>
    </div>
</body>
</html>

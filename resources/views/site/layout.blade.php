<!DOCTYPE html>
<html lang="tr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Sayfaya özel başlık/açıklama/canonical denetleyiciden gelir; yoksa site geneli kullanılır.
        $metaBaslik = $baslik ?? config('firma.ad') . ' — ' . config('site.slogan');
        $metaAciklama = $aciklama ?? config('site.aciklama');
        $metaUrl = $canonical ?? url()->current();
    @endphp
    <title>{{ $metaBaslik }}</title>
    <meta name="description" content="{{ $metaAciklama }}">
    @if (! empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ $metaUrl }}">
    <meta name="theme-color" content="#0a2a1d">
    @if (config('site.google_dogrulama'))
        <meta name="google-site-verification" content="{{ config('site.google_dogrulama') }}">
    @endif
    <meta property="og:site_name" content="{{ config('firma.ad') }}">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:title" content="{{ $metaBaslik }}">
    <meta property="og:description" content="{{ $metaAciklama }}">
    <meta property="og:image" content="{{ asset('images/logo-640.png') }}">
    <meta property="og:image:width" content="640">
    <meta property="og:image:height" content="640">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-krem-50 font-sans text-stone-800 antialiased">
    @php
        $firma = config('firma');
        $site = config('site');
        $menu = [
            route('site.katalog') => 'Ürünler',
            '#neden-biz' => 'Neden Biz',
            '#hakkimizda' => 'Hakkımızda',
            '#iletisim' => 'İletişim',
        ];
        $telefonHam = preg_replace('/\D+/', '', (string) $firma['telefon']);
        $whatsapp = preg_replace('/\D+/', '', (string) $site['whatsapp']);
    @endphp

    {{-- Sabit üst menü: slider üzerinde saydam, kaydırınca logo yeşiline döner (JS: app.js) --}}
    <header @class(['site-header', 'is-solid' => $headerSabit ?? false]) data-header @if ($headerSabit ?? false) data-solid @endif>
        <div class="relative mx-auto flex h-full max-w-7xl items-center px-4 sm:px-6 lg:px-10">
            <button type="button" class="burger mr-3 flex size-10 flex-col items-center justify-center gap-[5px] rounded-md md:hidden" data-menu-toggle aria-label="Menü" aria-expanded="false" aria-controls="mobil-menu">
                <span></span><span></span><span></span>
            </button>

            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo-160.png') }}" alt="{{ $firma['ad'] }} logosu" width="44" height="44" class="size-11 rounded-md ring-1 ring-altin-500/40">
                <span class="font-display text-lg font-semibold tracking-wide text-altin-300 sm:text-xl">{{ $firma['ad'] }}</span>
            </a>

            <nav class="absolute left-1/2 hidden -translate-x-1/2 items-center md:flex" aria-label="Ana menü">
                @foreach ($menu as $href => $etiket)
                    <a href="{{ str_starts_with($href, '#') ? url('/') . $href : $href }}" class="nav-link">{{ $etiket }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-3 sm:gap-4">
                @if ($telefonHam)
                    <a href="tel:{{ $telefonHam }}" class="hidden items-center gap-2 text-sm text-white/85 transition hover:text-white lg:flex">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                        {{ $firma['telefon'] }}
                    </a>
                @endif
                @if ($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full border border-white/25 text-white transition hover:border-altin-400 hover:text-altin-300" aria-label="WhatsApp">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.6 0 .3 5.3.3 11.8c0 2.1.5 4.1 1.6 5.9L0 24l6.4-1.7a11.8 11.8 0 0 0 5.7 1.4c6.5 0 11.8-5.3 11.8-11.8 0-3.2-1.2-6.1-3.4-8.4ZM12.1 21.7c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.8 1 1-3.7-.2-.4a9.8 9.8 0 0 1-1.5-5.2c0-5.4 4.4-9.8 9.9-9.8 2.6 0 5.1 1 6.9 2.9a9.7 9.7 0 0 1 2.9 6.9c0 5.4-4.4 9.9-9.8 9.9Zm5.4-7.3c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4Z"/></svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Mobil menü paneli --}}
        <div id="mobil-menu" class="mobile-menu absolute inset-x-0 top-full border-t border-altin-500/30 bg-yesil-900 shadow-xl md:hidden" data-menu>
            <nav class="mx-auto flex max-w-7xl flex-col px-4 py-2 sm:px-6" aria-label="Mobil menü">
                @foreach ($menu as $href => $etiket)
                    <a href="{{ str_starts_with($href, '#') ? url('/') . $href : $href }}" class="border-b border-white/10 py-3.5 text-sm uppercase tracking-[0.18em] text-white/90 last:border-0 hover:text-altin-300">{{ $etiket }}</a>
                @endforeach
                @if ($telefonHam)
                    <a href="tel:{{ $telefonHam }}" class="py-3.5 text-sm text-white/80">{{ $firma['telefon'] }}</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        @yield('icerik')
    </main>

    <footer class="bg-yesil-950 text-krem-100/70">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex flex-col items-start justify-between gap-6 py-10 text-sm sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-160.png') }}" alt="" width="40" height="40" class="size-10 rounded-md ring-1 ring-altin-500/30">
                    <div>
                        <div class="font-display font-semibold tracking-wide text-altin-300">{{ $firma['ad'] }}</div>
                        @if ($firma['adres'])<div>{{ $firma['adres'] }}</div>@endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    @if ($telefonHam)<a href="tel:{{ $telefonHam }}" class="hover:text-altin-300">{{ $firma['telefon'] }}</a>@endif
                    @if ($firma['eposta'])<a href="mailto:{{ $firma['eposta'] }}" class="hover:text-altin-300">{{ $firma['eposta'] }}</a>@endif
                    <a href="{{ url('/panel') }}" rel="nofollow" class="inline-flex items-center gap-1.5 font-medium text-altin-300 hover:text-altin-200">
                        <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a4 4 0 0 0-4 4v2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1V6a4 4 0 0 0-4-4Zm2 6V6a2 2 0 1 0-4 0v2h4Z" clip-rule="evenodd"/></svg>
                        Giriş
                    </a>
                </div>
            </div>
            <div class="flex flex-col items-center justify-between gap-2 border-t border-white/10 py-5 text-xs text-krem-100/50 sm:flex-row">
                <p>&copy; {{ date('Y') }} {{ $firma['ad'] }}. Tüm hakları saklıdır.</p>
                <p>{{ $site['slogan'] }}</p>
            </div>
        </div>
    </footer>
</body>
</html>

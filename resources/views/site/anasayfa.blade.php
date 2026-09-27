@extends('site.layout')

@section('icerik')
    @php
        $firma = config('firma');
        $site = config('site');
        $whatsapp = preg_replace('/\D+/', '', (string) $site['whatsapp']);
        $telefonHam = preg_replace('/\D+/', '', (string) $firma['telefon']);
    @endphp

    {{-- Hero slider: tam ekran, otomatik geçişli; slaytlar config/site.php > slaytlar --}}
    @php $slaytlar = array_values($site['slaytlar'] ?? []); @endphp
    @push('head')
        @if (! empty($slaytlar[0]['gorsel']))
            {{-- İlk slayt sayfanın en büyük görseli: tarayıcı CSS'i beklemeden yüklemeye başlasın. --}}
            <link rel="preload" as="image" href="{{ asset($slaytlar[0]['gorsel']) }}" fetchpriority="high">
        @endif
        <script type="application/ld+json">{!! json_encode($yapisalVeri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush
    <section class="hero" data-slider data-autoplay="6000" aria-roledescription="carousel" aria-label="Tanıtım" tabindex="0">
        @foreach ($slaytlar as $i => $slayt)
            <div @class(['hero-slide', 'is-active' => $i === 0])
                 @if ($i === 0) style="background-image: url('{{ asset($slayt['gorsel']) }}')" @else data-bg="{{ asset($slayt['gorsel']) }}" @endif
                 role="group" aria-roledescription="slayt" aria-label="{{ $i + 1 }} / {{ count($slaytlar) }}">
                <div class="hero-slide-inner">
                    @if (! empty($slayt['ust_baslik']))
                        <p class="hero-eyebrow">{{ $slayt['ust_baslik'] }}</p>
                    @endif
                    @if ($i === 0)
                        <h1 class="hero-title">{{ $slayt['baslik'] }}</h1>
                    @else
                        <h2 class="hero-title">{{ $slayt['baslik'] }}</h2>
                    @endif
                    @if (! empty($slayt['aciklama']))
                        <p class="hero-text">{{ $slayt['aciklama'] }}</p>
                    @endif
                    @if (! empty($slayt['buton']))
                        <a href="{{ str_starts_with($slayt['baglanti'] ?? '#', '#') ? url('/') . ($slayt['baglanti'] ?? '#urunler') : $slayt['baglanti'] }}" class="hero-btn">{{ $slayt['buton'] }}</a>
                    @endif
                </div>
            </div>
        @endforeach

        @if (count($slaytlar) > 1)
            <button type="button" class="hero-arrow hero-arrow-prev" data-prev aria-label="Önceki slayt">&#8249;</button>
            <button type="button" class="hero-arrow hero-arrow-next" data-next aria-label="Sonraki slayt">&#8250;</button>
            <div class="hero-dots" data-dots></div>
        @endif
    </section>
    {{-- Ürünler: sağa doğru akan şerit (üzerine gelince durur); kartlar SiteController::vitrinKartlari --}}
    <section id="urunler" class="scroll-mt-20 overflow-hidden bg-krem-50 py-16">
        <div class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-6 px-4 sm:px-6">
            <div class="max-w-2xl">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-altin-600">Ürünler</p>
                <h2 class="font-display text-3xl font-semibold tracking-wide text-yesil-900">Ürünlerimiz</h2>
                <p class="mt-3 text-stone-600">Perde işinde ihtiyaç duyduğunuz her kalemi tek çatı altında topluyoruz.</p>
            </div>
            <a href="{{ route('site.katalog') }}" class="inline-flex items-center gap-2 rounded-lg bg-yesil-900 px-5 py-3 text-sm font-semibold text-altin-300 shadow-sm transition hover:bg-yesil-800">
                Tüm kataloğu gör
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
            </a>
        </div>

        @php $sure = max(30, count($vitrin) * 5); @endphp
        <div class="akis mt-10" aria-label="Ürünlerimizden örnekler">
            @foreach ([1, 2] as $kopya)
                <div class="akis-grup" style="--sure: {{ $sure }}s" @if ($kopya === 2) aria-hidden="true" @endif>
                    @foreach ($vitrin as $kart)
                        <a href="{{ $kart['link'] }}" class="akis-kart" @if ($kopya === 2) tabindex="-1" @endif>
                            <div class="akis-kart-gorsel">
                                @if ($kart['gorsel'])
                                    <img src="{{ $kart['gorsel'] }}" alt="{{ $kart['ad'] }}" loading="lazy" class="size-full object-cover">
                                @else
                                    <span class="font-display text-5xl font-semibold text-altin-300/80">{{ $kart['harf'] }}</span>
                                @endif
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-yesil-900">{{ $kart['ad'] }}</h3>
                                @if ($kart['alt'])<p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $kart['alt'] }}</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    {{-- Neden biz --}}
    <section id="neden-biz" class="scroll-mt-20 bg-yesil-950 text-krem-100">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-altin-400">Neden biz</p>
            <h2 class="font-display text-3xl font-semibold tracking-wide text-krem-50">Neden {{ $firma['ad'] }}?</h2>
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($site['neden_biz'] as $madde)
                    <div>
                        <div class="mb-3 h-px w-12 bg-gradient-to-r from-altin-500 to-altin-300"></div>
                        <h3 class="text-lg font-semibold text-altin-200">{{ $madde['baslik'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-krem-100/70">{{ $madde['aciklama'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Hakkımızda --}}
    <section id="hakkimizda" class="scroll-mt-20 bg-krem-100">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-altin-600">Biz kimiz</p>
                <h2 class="font-display text-3xl font-semibold tracking-wide text-yesil-900">Hakkımızda</h2>
                <div class="mt-5 space-y-4 text-stone-600">
                    @foreach ($site['hakkimizda'] as $paragraf)
                        <p>{{ $paragraf }}</p>
                    @endforeach
                </div>
            </div>
            <div class="rounded-2xl border border-altin-300 bg-white p-6 shadow-sm">
                <h3 class="font-display font-semibold tracking-wide text-yesil-900">Nasıl çalışıyoruz?</h3>
                <ol class="mt-4 space-y-3 text-sm text-stone-700">
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-yesil-900 text-xs font-bold text-altin-300">1</span> Telefon veya WhatsApp'tan ihtiyacınızı iletin; numune için bize danışın.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-yesil-900 text-xs font-bold text-altin-300">2</span> Toptan fiyat teklifimizi ve stok durumunu aynı gün bildirelim.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-yesil-900 text-xs font-bold text-altin-300">3</span> Siparişiniz hazırlanır, kargo veya ambarla adresinize ulaşır.</li>
                </ol>
            </div>
        </div>
    </section>

    {{-- İletişim --}}
    <section id="iletisim" class="scroll-mt-20 border-t border-krem-200 bg-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-altin-600">Bize ulaşın</p>
                <h2 class="font-display text-3xl font-semibold tracking-wide text-yesil-900">İletişim</h2>
                <p class="mt-3 text-stone-600">Toptan fiyat listesi ve numune için bize ulaşın.</p>
                <dl class="mt-8 space-y-5 text-stone-700">
                    @if ($firma['adres'])
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">Adres</dt><dd class="mt-1">{{ $firma['adres'] }}</dd></div>
                    @endif
                    @if ($firma['telefon'])
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">Telefon</dt><dd class="mt-1"><a href="tel:{{ $telefonHam }}" class="font-medium hover:text-altin-600">{{ $firma['telefon'] }}</a></dd></div>
                    @endif
                    @if ($whatsapp)
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">WhatsApp</dt><dd class="mt-1"><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="font-medium hover:text-altin-600">Mesaj gönderin</a></dd></div>
                    @endif
                    @if ($firma['eposta'])
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">E-posta</dt><dd class="mt-1"><a href="mailto:{{ $firma['eposta'] }}" class="font-medium hover:text-altin-600">{{ $firma['eposta'] }}</a></dd></div>
                    @endif
                    @if ($site['instagram'])
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">Instagram</dt><dd class="mt-1"><a href="https://instagram.com/{{ ltrim($site['instagram'], '@') }}" target="_blank" rel="noopener" class="font-medium hover:text-altin-600">{{ '@' . ltrim($site['instagram'], '@') }}</a></dd></div>
                    @endif
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-yesil-700">Çalışma saatleri</dt><dd class="mt-1">{{ $site['calisma_saatleri'] }}</dd></div>
                </dl>
            </div>
            <div class="overflow-hidden rounded-2xl border border-krem-200 bg-krem-100">
                @if ($site['harita_embed'])
                    <iframe src="{{ $site['harita_embed'] }}" class="h-full min-h-80 w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Konum"></iframe>
                @else
                    <div class="grid h-full min-h-80 place-items-center p-8 text-center text-sm text-stone-500">
                        Harita için .env dosyasına <code class="rounded bg-white px-1.5 py-0.5">SITE_HARITA_EMBED</code> adresini ekleyin.
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@extends('site.layout')

@section('icerik')
    @php
        $firma = config('firma');
        $site = config('site');
        $whatsapp = preg_replace('/\D+/', '', (string) $site['whatsapp']);
        $telefonHam = preg_replace('/\D+/', '', (string) $firma['telefon']);
    @endphp

    {{-- Başlık ve arama --}}
    <section class="bg-yesil-900 pb-12 pt-32 text-krem-50">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.25em] text-altin-400">Ürünler</p>
            <h1 class="font-display text-4xl font-semibold tracking-wide sm:text-5xl">Ürün Kataloğu</h1>
            <p class="mt-4 max-w-2xl text-krem-100/75">Aktif ürünlerimizi inceleyin. Toptan fiyat listesi, stok durumu ve numune için bizimle iletişime geçin.</p>

            <form method="get" action="{{ route('site.katalog') }}" class="mt-8 flex max-w-xl gap-2">
                @if ($seciliKategori)
                    <input type="hidden" name="kategori" value="{{ $seciliKategori->id }}">
                @endif
                <label class="sr-only" for="q">Ürün ara</label>
                <input id="q" name="q" value="{{ $arama }}" type="search" placeholder="Ürün adı ara" class="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-sm text-white placeholder:text-white/50 focus:border-altin-400 focus:outline-none focus:ring-2 focus:ring-altin-400/40">
                <button type="submit" class="rounded-lg bg-altin-500 px-5 py-2.5 text-sm font-semibold text-yesil-950 transition hover:bg-altin-400">Ara</button>
            </form>
        </div>
    </section>

    {{-- Kategori süzgeci --}}
    <section class="border-b border-krem-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap gap-2 px-4 py-4 sm:px-6">
            <a href="{{ route('site.katalog', array_filter(['q' => $arama])) }}" @class(['katalog-etiket', 'is-active' => ! $seciliKategori])>Tümü</a>
            @foreach ($kategoriler as $kategori)
                <a href="{{ route('site.katalog', array_filter(['kategori' => $kategori->id, 'q' => $arama])) }}" @class(['katalog-etiket', 'is-active' => $seciliKategori?->is($kategori)])>
                    {{ $kategori->ad }} <span class="ml-1 opacity-60">{{ $kategori->urunler_count }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Ürünler --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="font-display text-2xl font-semibold tracking-wide text-yesil-900">
                {{ $seciliKategori?->ad ?? 'Tüm ürünler' }}
            </h2>
            <p class="text-sm text-stone-500">
                {{ $urunler->total() }} ürün
                @if ($arama !== '') · "{{ $arama }}" için sonuçlar @endif
            </p>
        </div>

        @if ($urunler->isEmpty())
            <div class="rounded-2xl border border-dashed border-krem-200 bg-krem-50 p-12 text-center text-stone-500">
                Bu ölçütlere uygun ürün bulunamadı.
                <a href="{{ route('site.katalog') }}" class="ml-1 font-medium text-yesil-700 hover:text-altin-600">Tüm ürünleri gör</a>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($urunler as $urun)
                    <article class="urun-kart">
                        <div class="urun-kart-gorsel">
                            @if ($urun->gorsel)
                                <img src="{{ asset('storage/' . $urun->gorsel) }}" alt="{{ $urun->ad }}" loading="lazy" class="size-full object-cover">
                            @else
                                <span class="font-display text-5xl font-semibold text-altin-300/80">{{ mb_substr($urun->ad, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.15em] text-altin-600">{{ $urun->kategori?->ad }}</div>
                            <h3 class="mt-1 font-semibold text-yesil-900">{{ $urun->ad }}</h3>
                            @if ($urun->aciklama)
                                <p class="mt-3 line-clamp-2 text-sm text-stone-600">{{ $urun->aciklama }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $urunler->links() }}
            </div>
        @endif
    </section>

    {{-- Fiyat / numune çağrısı --}}
    <section class="bg-yesil-950 text-krem-100">
        <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-4 py-12 sm:flex-row sm:items-center sm:px-6">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-wide text-krem-50">Fiyat ve stok bilgisi için bize ulaşın</h2>
                <p class="mt-2 text-krem-100/70">Toptan fiyat listemizi paylaşırız; numune için bizimle iletişime geçin.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="rounded-lg bg-altin-500 px-5 py-3 text-sm font-semibold text-yesil-950 transition hover:bg-altin-400">WhatsApp'tan yazın</a>
                @endif
                @if ($telefonHam)
                    <a href="tel:{{ $telefonHam }}" class="rounded-lg border border-altin-500/50 px-5 py-3 text-sm font-semibold text-altin-200 transition hover:border-altin-400 hover:bg-yesil-800">{{ $firma['telefon'] }}</a>
                @else
                    <a href="{{ url('/') }}#iletisim" class="rounded-lg border border-altin-500/50 px-5 py-3 text-sm font-semibold text-altin-200 transition hover:border-altin-400 hover:bg-yesil-800">İletişim</a>
                @endif
            </div>
        </div>
    </section>
@endsection

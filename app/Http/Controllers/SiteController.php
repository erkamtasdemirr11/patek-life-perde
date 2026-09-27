<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Urun;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Tanıtım sitesi: ana sayfa, herkese açık ürün kataloğu, robots.txt ve sitemap.xml.
 * Katalogda yalnızca aktif ürünler görünür; fiyat ve stok gösterilmez.
 */
class SiteController extends Controller
{
    public function anasayfa(): View
    {
        return view('site.anasayfa', [
            'vitrin' => $this->vitrinKartlari(),
            'yapisalVeri' => $this->yapisalVeri(),
        ]);
    }

    public function katalog(Request $request): View
    {
        $kategoriId = $request->integer('kategori') ?: null;
        $arama = trim((string) $request->string('q'));
        $sayfa = $request->integer('page');

        $kategoriler = Kategori::query()
            ->whereHas('urunler', fn (Builder $q) => $q->secilebilir())
            ->withCount(['urunler' => fn (Builder $q) => $q->secilebilir()])
            ->orderBy('sira')
            ->orderBy('ad')
            ->get();

        $urunler = Urun::query()
            ->secilebilir()
            ->with('kategori')
            ->when($kategoriId, fn (Builder $q) => $q->where('kategori_id', $kategoriId))
            ->when($arama !== '', fn (Builder $q) => $q->where('ad', 'like', "%{$arama}%"))
            ->orderByRaw('gorsel IS NULL')
            ->orderBy('ad')
            ->paginate(24)
            ->withQueryString();

        $seciliKategori = $kategoriler->firstWhere('id', $kategoriId);
        $firma = config('firma.ad');

        return view('site.katalog', [
            'kategoriler' => $kategoriler,
            'urunler' => $urunler,
            'seciliKategori' => $seciliKategori,
            'arama' => $arama,
            'headerSabit' => true,
            // SEO: kategoriye özel başlık ve açıklama, canonical adres; arama sonuçları dizine eklenmez.
            'baslik' => ($seciliKategori ? "{$seciliKategori->ad} — " : '') . "Ürün Kataloğu — {$firma}",
            'aciklama' => $seciliKategori
                ? "{$seciliKategori->ad} grubundaki toptan ürünlerimiz. Fiyat listesi ve numune için {$firma} ile iletişime geçin."
                : 'Toptan tül, fon, stor ve perde aksesuarı kataloğu. Aktif ürünlerimizi inceleyin; fiyat listesi ve numune için bize ulaşın.',
            'canonical' => route('site.katalog', array_filter(['kategori' => $kategoriId, 'page' => $sayfa > 1 ? $sayfa : null])),
            'robots' => $arama !== '' ? 'noindex,follow' : null,
        ]);
    }

    /** Arama motorları: panel, Livewire ve sağlık ucu taranmaz; sitemap adresi verilir. */
    public function robots(): Response
    {
        $satirlar = [
            'User-agent: *',
            'Disallow: /panel',
            'Disallow: /livewire',
            'Disallow: /up',
            '',
            'Sitemap: ' . route('site.sitemap'),
        ];

        return response(implode("\n", $satirlar) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Ana sayfa, katalog ve aktif ürünü olan her kategori sayfası. */
    public function sitemap(): Response
    {
        $kategoriler = Kategori::query()
            ->whereHas('urunler', fn (Builder $q) => $q->secilebilir())
            ->orderBy('sira')
            ->orderBy('ad')
            ->get();

        $sonGuncelleme = Urun::query()->secilebilir()->max('updated_at');

        return response()
            ->view('site.sitemap', [
                'kategoriler' => $kategoriler,
                'sonGuncelleme' => $sonGuncelleme ? Carbon::parse($sonGuncelleme)->toDateString() : null,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Ana sayfadaki akan şerit için kartlar. Görselli ürünler öne alınır;
     * yeterli ürün yoksa yapılandırmadaki ürün grupları gösterilir.
     *
     * @return list<array{ad: string, alt: ?string, gorsel: ?string, harf: string, link: string}>
     */
    protected function vitrinKartlari(): array
    {
        $urunler = Urun::query()
            ->secilebilir()
            ->with('kategori')
            ->orderByRaw('gorsel IS NULL')
            ->latest('id')
            ->limit(12)
            ->get();

        if ($urunler->count() >= 4) {
            return $urunler->map(fn (Urun $u): array => [
                'ad' => $u->ad,
                'alt' => $u->kategori?->ad,
                'gorsel' => $u->gorsel ? asset('storage/' . $u->gorsel) : null,
                'harf' => mb_substr($u->ad, 0, 1),
                'link' => route('site.katalog', ['kategori' => $u->kategori_id]),
            ])->all();
        }

        return collect(config('site.urun_gruplari', []))->map(fn (array $grup): array => [
            'ad' => $grup['ad'],
            'alt' => $grup['aciklama'] ?? null,
            'gorsel' => null,
            'harf' => mb_substr($grup['ad'], 0, 1),
            'link' => route('site.katalog'),
        ])->all();
    }

    /**
     * Google için yapısal veri (JSON-LD): işletme adı, iletişim ve adres.
     * .env'de boş bırakılan alanlar çıktıya girmez.
     *
     * @return array<string, mixed>
     */
    protected function yapisalVeri(): array
    {
        $firma = config('firma');
        $instagram = ltrim((string) config('site.instagram'), '@');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $firma['ad'],
            'description' => config('site.aciklama'),
            'url' => url('/'),
            'logo' => asset('images/logo-640.png'),
            'image' => asset('images/logo-640.png'),
            'telephone' => $firma['telefon'] ?: null,
            'email' => $firma['eposta'] ?: null,
            'address' => $firma['adres'] ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $firma['adres'],
                'addressCountry' => 'TR',
            ] : null,
            'sameAs' => $instagram !== '' ? ['https://instagram.com/' . $instagram] : null,
        ]);
    }
}

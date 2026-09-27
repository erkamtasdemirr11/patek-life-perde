{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('site.katalog') }}</loc>
@if ($sonGuncelleme)
        <lastmod>{{ $sonGuncelleme }}</lastmod>
@endif
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@foreach ($kategoriler as $kategori)
    <url>
        <loc>{{ route('site.katalog', ['kategori' => $kategori->id]) }}</loc>
@if ($sonGuncelleme)
        <lastmod>{{ $sonGuncelleme }}</lastmod>
@endif
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
</urlset>

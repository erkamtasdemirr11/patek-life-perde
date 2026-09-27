<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Fiş {{ $fis->fis_no }}</title>
    {{--
        80 mm termal rulo. Sayfa, rulonun baskı alanı kadardır (72 mm); kâğıdın iki yanındaki boşluğu
        yazıcı kendisi bıraktığı için burada yalnızca 2 mm pay vardır. Sayfa boyunu FisPdfController
        içeriğe göre ayarlar; ölçüm body yüksekliğinden yapıldığı için boşluklar @page yerine body'de durur.
        Termal yazıcı yalnızca siyah basar: renk ve gri ton kullanılmaz. Tepedeki amblem, renkli logonun
        siyah-beyaz halidir (images/logo-termal.png); renkli logo koyu zeminli olduğu için burada kullanılmaz.
    --}}
    <style>
        @page { margin: 0; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; padding: 3mm 2mm 8mm; font-size: 9pt; line-height: 1.25; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0.4mm 0; vertical-align: top; }
        .orta { text-align: center; }
        .sag { text-align: right; white-space: nowrap; }
        .kalin { font-weight: bold; }
        .kucuk { font-size: 7.5pt; }
        .logo { height: 26mm; margin-bottom: 1mm; }
        .firma { font-size: 13pt; font-weight: bold; }
        .baslik { font-size: 10pt; font-weight: bold; letter-spacing: 1pt; }
        .cizgi { border-top: 1px dashed #000; margin: 2mm 0; }
        .kalem td { padding-bottom: 1mm; }
        .genel td { font-size: 12pt; font-weight: bold; padding-top: 1mm; }
        .sonuc td { font-weight: bold; border-top: 1px solid #000; padding-top: 1mm; }
        .iptal { border: 2px solid #000; text-align: center; font-size: 16pt; font-weight: bold; letter-spacing: 3pt; padding: 1mm 0; margin: 2mm 0; }
        .imza td { width: 50%; text-align: center; padding-top: 14mm; font-size: 7.5pt; }
        .imza span { border-top: 1px solid #000; padding: 1mm 4mm 0; }
    </style>
</head>
<body>
    @php
        $ozet = $fis->bakiyeOzeti();
        $pesin = $ozet['pesin'];
    @endphp

    <div class="orta">
        <img class="logo" src="{{ public_path('images/logo-termal.png') }}" alt="">
        <div class="firma">{{ $firma['ad'] }}</div>
        @if ($firma['adres'])<div class="kucuk">{{ $firma['adres'] }}</div>@endif
        @if ($firma['telefon'])<div class="kucuk">Tel: {{ $firma['telefon'] }}</div>@endif
    </div>

    @if ($fis->iptalMi())
        <div class="iptal">İPTAL</div>
    @endif

    <div class="cizgi"></div>

    <div class="orta baslik">SATIŞ FİŞİ</div>
    <table>
        <tr>
            <td>Fiş no</td>
            <td class="sag kalin">{{ $fis->fis_no }}</td>
        </tr>
        <tr>
            <td>Tarih</td>
            <td class="sag">{{ $fis->tarih->format('d.m.Y H:i') }}</td>
        </tr>
        <tr>
            <td>Ödeme</td>
            <td class="sag">{{ $fis->odeme_tipi->getLabel() }}@if ($pesin) ({{ $pesin->odeme_yontemi->getLabel() }})@endif</td>
        </tr>
        <tr>
            <td>Kesen</td>
            <td class="sag">{{ $fis->user->name }}</td>
        </tr>
    </table>

    <div class="cizgi"></div>

    <div class="kucuk">MÜŞTERİ</div>
    <div class="kalin">{{ $fis->musteri->firma_adi }}</div>
    @if ($fis->musteri->yetkili)<div>{{ $fis->musteri->yetkili }}</div>@endif
    @if ($fis->musteri->telefon)<div>Tel: {{ $fis->musteri->telefon }}</div>@endif
    @if ($fis->musteri->adres)<div class="kucuk">{{ $fis->musteri->adres }}</div>@endif

    @if ($fis->aciklama)
        <div class="kucuk" style="margin-top: 1mm;">Açıklama: {{ $fis->aciklama }}</div>
    @endif

    <div class="cizgi"></div>

    {{-- Dar kâğıtta her kalem iki satır: üstte ürün adı, altta miktar × fiyat ve tutar. --}}
    <table>
        @foreach ($fis->kalemler as $kalem)
            <tr>
                <td colspan="2" class="kalin">
                    {{ $loop->iteration }}. {{ $kalem->urun->ad }}@if ($kalem->urun->renk) &mdash; {{ $kalem->urun->renk }}@endif
                </td>
            </tr>
            <tr class="kalem">
                <td>{{ \App\Support\Bicim::miktar($kalem->miktar, $kalem->urun->birim) }} &times; {{ \App\Support\Bicim::para($kalem->birim_fiyat) }}</td>
                <td class="sag">{{ \App\Support\Bicim::para($kalem->tutar) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="cizgi"></div>

    <table>
        <tr>
            <td>Ara toplam</td>
            <td class="sag">{{ \App\Support\Bicim::para($fis->ara_toplam) }}</td>
        </tr>
        @if (bccomp((string) $fis->iskonto, '0', 2) > 0)
            <tr>
                <td>İskonto</td>
                <td class="sag">&minus; {{ \App\Support\Bicim::para($fis->iskonto) }}</td>
            </tr>
        @endif
        <tr class="genel">
            <td>TOPLAM</td>
            <td class="sag">{{ \App\Support\Bicim::para($fis->genel_toplam) }}</td>
        </tr>
    </table>

    <div class="cizgi"></div>

    {{-- Cari hesap: fiş anındaki durum. Önceki bakiye borçsa fiş tutarı üstüne eklenir, alacaksa alacaktan düşer. --}}
    <div class="kucuk">CARİ HESAP DURUMU</div>
    <table>
        <tr>
            <td>Önceki bakiye</td>
            <td class="sag">{{ \App\Support\Bicim::bakiye($ozet['onceki']) }}</td>
        </tr>
        <tr>
            <td>Bu fiş</td>
            <td class="sag">+ {{ \App\Support\Bicim::para($fis->genel_toplam) }}</td>
        </tr>
        @if ($pesin)
            <tr>
                <td>
                    Peşin tahsilat
                    <div class="kucuk">Makbuz {{ $pesin->makbuz_no }}@if ($pesin->iptalMi()) (iptal edildi)@endif</div>
                </td>
                <td class="sag">@if ($pesin->aktifMi())&minus; {{ \App\Support\Bicim::para($pesin->tutar) }}@else&mdash;@endif</td>
            </tr>
        @endif
        <tr class="sonuc">
            <td>Fiş sonrası bakiye</td>
            <td class="sag">{{ \App\Support\Bicim::bakiye($ozet['sonrasi']) }}</td>
        </tr>
    </table>
    <div class="kucuk" style="margin-top: 1mm;">
        @if ($pesin && $pesin->aktifMi())
            Bu fiş peşin ödenmiştir; hesaba borç yazılmamıştır.
        @elseif (bccomp($ozet['sonrasi'], '0', 2) > 0)
            Bu fiş açık hesaba işlenmiştir. Müşterinin toplam borcu yukarıdaki tutardır.
        @elseif (bccomp($ozet['sonrasi'], '0', 2) < 0)
            Bu fiş açık hesaba işlenmiştir. Hesap alacaklı kalmıştır.
        @else
            Bu fiş açık hesaba işlenmiştir. Hesap kapalıdır.
        @endif
    </div>

    @if ($fis->iptalMi())
        <div class="iptal">İPTAL</div>
        <div class="kucuk">
            <span class="kalin">Bu fiş iptal edilmiştir.</span>
            {{ $fis->iptal_tarihi?->format('d.m.Y H:i') }} &mdash; {{ $fis->iptalEden?->name }}
            <br>Neden: {{ $fis->iptal_nedeni }}
        </div>
    @endif

    <table class="imza">
        <tr>
            <td><span>Teslim eden</span></td>
            <td><span>Teslim alan</span></td>
        </tr>
    </table>

    <div class="cizgi"></div>
    <div class="orta kucuk">Bu belge mali değer taşımaz, bilgi amaçlıdır.</div>
</body>
</html>

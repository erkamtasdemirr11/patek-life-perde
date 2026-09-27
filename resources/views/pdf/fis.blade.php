<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Fiş {{ $fis->fis_no }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .ust { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .ust td { vertical-align: top; }
        .sag { text-align: right; }
        .kutu { border: 1px solid #999; padding: 8px 10px; }
        .etiket { color: #666; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
        table.kalemler { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.kalemler th { background: #f0f0f0; border-bottom: 1px solid #999; padding: 6px 6px; text-align: left; font-size: 10px; }
        table.kalemler td { border-bottom: 1px solid #ddd; padding: 6px 6px; }
        table.kalemler .no { width: 24px; color: #666; }
        table.toplam { margin-top: 10px; margin-left: auto; width: 45%; border-collapse: collapse; }
        table.toplam td { padding: 4px 6px; }
        table.toplam tr.genel td { border-top: 2px solid #111; font-weight: bold; font-size: 13px; }
        table.cari { margin-top: 10px; margin-left: auto; width: 45%; border-collapse: collapse; border: 1px solid #999; background: #fafafa; }
        table.cari td { padding: 4px 8px; }
        table.cari td.baslik { padding: 6px 8px 2px; border-bottom: 1px solid #ddd; }
        table.cari tr.sonuc td { border-top: 1px solid #999; font-weight: bold; font-size: 12px; padding-top: 6px; padding-bottom: 6px; }
        table.cari .not { color: #666; font-size: 9px; padding: 0 8px 6px; }
        .borc { color: #b91c1c; }
        .alacak { color: #15803d; }
        .ibare { margin-top: 26px; padding-top: 8px; border-top: 1px dashed #999; color: #555; font-size: 9.5px; text-align: center; }
        .imza { width: 100%; margin-top: 34px; }
        .imza td { width: 50%; text-align: center; padding-top: 30px; border-top: 1px solid #999; color: #666; font-size: 10px; }
        .damga {
            position: fixed; top: 38%; left: 22%;
            transform: rotate(-28deg);
            border: 8px solid rgba(220, 38, 38, .55);
            color: rgba(220, 38, 38, .55);
            font-size: 88px; font-weight: bold; letter-spacing: .12em;
            padding: 10px 40px;
        }
        .iptal-notu { margin-top: 12px; padding: 8px 10px; border: 1px solid #dc2626; color: #991b1b; }
    </style>
</head>
<body>
    @php
        $ozet = $fis->bakiyeOzeti();
        $pesin = $ozet['pesin'];
        $yonSinifi = fn (string $bakiye): string => match (bccomp($bakiye, '0', 2)) { 1 => 'borc', -1 => 'alacak', default => '' };
    @endphp

    @if ($fis->iptalMi())
        <div class="damga">İPTAL</div>
    @endif

    <table class="ust">
        <tr>
            <td>
                <table style="border-collapse: collapse;"><tr>
                    <td style="padding: 0 10px 0 0; vertical-align: middle;"><img src="{{ public_path('images/logo-pdf.png') }}" alt="" style="height: 22mm; width: 22mm;"></td>
                    <td style="vertical-align: middle;">
                <h1>{{ $firma['ad'] }}</h1>
                @if ($firma['adres'])<div>{{ $firma['adres'] }}</div>@endif
                @if ($firma['telefon'])<div>Tel: {{ $firma['telefon'] }}</div>@endif
                @if ($firma['eposta'])<div>{{ $firma['eposta'] }}</div>@endif
                    </td>
                </tr></table>
            </td>
            <td class="sag">
                <div class="etiket">Satış fişi</div>
                <div style="font-size: 18px; font-weight: bold;">{{ $fis->fis_no }}</div>
                <div>{{ $fis->tarih->format('d.m.Y H:i') }}</div>
                <div class="etiket" style="margin-top: 6px;">Ödeme</div>
                <div>{{ $fis->odeme_tipi->getLabel() }}@if ($pesin) ({{ $pesin->odeme_yontemi->getLabel() }})@endif</div>
                <div class="etiket" style="margin-top: 6px;">Kesen</div>
                <div>{{ $fis->user->name }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="width: 55%;">
                <div class="kutu">
                    <div class="etiket">Müşteri</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $fis->musteri->firma_adi }}</div>
                    @if ($fis->musteri->yetkili)<div>{{ $fis->musteri->yetkili }}</div>@endif
                    @if ($fis->musteri->telefon)<div>Tel: {{ $fis->musteri->telefon }}</div>@endif
                    @if ($fis->musteri->adres)<div>{{ $fis->musteri->adres }}</div>@endif
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td>
                @if ($fis->aciklama)
                    <div class="kutu">
                        <div class="etiket">Açıklama</div>
                        <div>{{ $fis->aciklama }}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="kalemler">
        <thead>
            <tr>
                <th class="no">#</th>
                <th>Ürün</th>
                <th class="sag">Miktar</th>
                <th class="sag">Birim fiyat</th>
                <th class="sag">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($fis->kalemler as $kalem)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>
                        {{ $kalem->urun->ad }}
                        @if ($kalem->urun->renk)
                            <span style="color:#666;">&mdash; {{ $kalem->urun->renk }}</span>
                        @endif
                    </td>
                    <td class="sag">{{ \App\Support\Bicim::miktar($kalem->miktar, $kalem->urun->birim) }}</td>
                    <td class="sag">{{ \App\Support\Bicim::para($kalem->birim_fiyat) }}</td>
                    <td class="sag">{{ \App\Support\Bicim::para($kalem->tutar) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="toplam">
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
            <td>Genel toplam</td>
            <td class="sag">{{ \App\Support\Bicim::para($fis->genel_toplam) }}</td>
        </tr>
    </table>

    {{-- Cari hesap: fiş anındaki durum. Önceki bakiye borçsa fiş tutarı üstüne eklenir, alacaksa alacaktan düşer. --}}
    <table class="cari">
        <tr>
            <td class="baslik etiket" colspan="2">Cari hesap durumu</td>
        </tr>
        <tr>
            <td>Önceki bakiye</td>
            <td class="sag {{ $yonSinifi($ozet['onceki']) }}">{{ \App\Support\Bicim::bakiye($ozet['onceki']) }}</td>
        </tr>
        <tr>
            <td>Bu fiş</td>
            <td class="sag">+ {{ \App\Support\Bicim::para($fis->genel_toplam) }}</td>
        </tr>
        @if ($pesin)
            <tr>
                <td>Peşin tahsilat &middot; Makbuz {{ $pesin->makbuz_no }}@if ($pesin->iptalMi()) (iptal edildi)@endif</td>
                <td class="sag">@if ($pesin->aktifMi())&minus; {{ \App\Support\Bicim::para($pesin->tutar) }}@else&mdash;@endif</td>
            </tr>
        @endif
        <tr class="sonuc">
            <td>Fiş sonrası bakiye</td>
            <td class="sag {{ $yonSinifi($ozet['sonrasi']) }}">{{ \App\Support\Bicim::bakiye($ozet['sonrasi']) }}</td>
        </tr>
        <tr>
            <td class="not" colspan="2">
                @if ($pesin && $pesin->aktifMi())
                    Bu fiş peşin ödenmiştir; hesaba borç yazılmamıştır.
                @elseif (bccomp($ozet['sonrasi'], '0', 2) > 0)
                    Bu fiş açık hesaba işlenmiştir. Müşterinin toplam borcu yukarıdaki tutardır.
                @elseif (bccomp($ozet['sonrasi'], '0', 2) < 0)
                    Bu fiş açık hesaba işlenmiştir. Müşterinin alacağı fiş tutarından fazla olduğu için hesap alacaklı kalmıştır.
                @else
                    Bu fiş açık hesaba işlenmiştir. Hesap kapalıdır.
                @endif
            </td>
        </tr>
    </table>

    @if ($fis->iptalMi())
        <div class="iptal-notu">
            <strong>Bu fiş iptal edilmiştir.</strong>
            {{ $fis->iptal_tarihi?->format('d.m.Y H:i') }} &mdash; {{ $fis->iptalEden?->name }}
            <br>Neden: {{ $fis->iptal_nedeni }}
        </div>
    @endif

    <table class="imza">
        <tr>
            <td>Teslim eden</td>
            <td>Teslim alan</td>
        </tr>
    </table>

    <div class="ibare">Bu belge mali değer taşımaz, bilgi amaçlıdır.</div>
</body>
</html>

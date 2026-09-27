<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Ekstre — {{ $musteri->firma_adi }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 10.5px; color: #111; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .ust { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .ust td { vertical-align: top; }
        .sag { text-align: right; }
        .kutu { border: 1px solid #999; padding: 8px 10px; }
        .etiket { color: #666; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
        table.hareketler { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.hareketler th { background: #f0f0f0; border-bottom: 1px solid #999; padding: 6px; text-align: left; font-size: 9.5px; }
        table.hareketler td { border-bottom: 1px solid #ddd; padding: 5px 6px; vertical-align: top; }
        table.hareketler tr.devreden td, table.hareketler tr.toplam td { background: #fafafa; font-weight: bold; }
        table.hareketler tr.kapanis td { border-top: 2px solid #111; font-weight: bold; font-size: 12px; }
        .borclu { color: #b91c1c; }
        .alacakli { color: #15803d; }
        .ibare { margin-top: 26px; padding-top: 8px; border-top: 1px dashed #999; color: #555; font-size: 9.5px; text-align: center; }
        .not { color: #666; font-size: 9px; margin-top: 6px; }
    </style>
</head>
<body>
    @php
        $para = fn ($v) => \App\Support\Bicim::para($v);
        $sinif = fn ($v) => bccomp((string) $v, '0', 2) > 0 ? 'borclu' : (bccomp((string) $v, '0', 2) < 0 ? 'alacakli' : '');
    @endphp

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
                <div class="etiket">Cari hesap ekstresi</div>
                <div style="font-size: 12px; margin-top: 4px;">
                    @if ($ekstre['baslangic'] || $ekstre['bitis'])
                        {{ $ekstre['baslangic']?->format('d.m.Y') ?? 'Başlangıç' }} — {{ $ekstre['bitis']?->format('d.m.Y') ?? 'Bugün' }}
                    @else
                        Tüm hareketler
                    @endif
                </div>
                <div class="etiket" style="margin-top: 6px;">Düzenlenme</div>
                <div>{{ now()->format('d.m.Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="width: 55%;">
                <div class="kutu">
                    <div class="etiket">Müşteri</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $musteri->firma_adi }}</div>
                    @if ($musteri->yetkili)<div>{{ $musteri->yetkili }}</div>@endif
                    @if ($musteri->telefon)<div>Tel: {{ $musteri->telefon }}</div>@endif
                    @if ($musteri->adres)<div>{{ $musteri->adres }}</div>@endif
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td>
                <div class="kutu">
                    <div class="etiket">Kapanış bakiyesi</div>
                    <div class="{{ $sinif($ekstre['kapanis']) }}" style="font-size: 16px; font-weight: bold;">{{ $para($ekstre['kapanis']) }}</div>
                    <div class="not">
                        @if (bccomp((string) $ekstre['kapanis'], '0', 2) > 0) Müşteri borçlu
                        @elseif (bccomp((string) $ekstre['kapanis'], '0', 2) < 0) Müşteri alacaklı (fazla ödeme)
                        @else Hesap kapalı @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="hareketler">
        <thead>
            <tr>
                <th style="width: 82px;">Tarih</th>
                <th>Açıklama</th>
                <th style="width: 80px;">Kaynak</th>
                <th class="sag" style="width: 80px;">Borç</th>
                <th class="sag" style="width: 80px;">Alacak</th>
                <th class="sag" style="width: 90px;">Bakiye</th>
            </tr>
        </thead>
        <tbody>
            <tr class="devreden">
                <td>{{ $ekstre['baslangic']?->format('d.m.Y') ?? '—' }}</td>
                <td colspan="4">Devreden bakiye</td>
                <td class="sag {{ $sinif($ekstre['devreden']) }}">{{ $para($ekstre['devreden']) }}</td>
            </tr>
            @forelse ($ekstre['satirlar'] as $satir)
                @php $h = $satir['hareket']; @endphp
                <tr>
                    <td>{{ $h->tarih->format('d.m.Y H:i') }}</td>
                    <td>{{ $h->aciklama ?: $h->kaynak->getLabel() }}</td>
                    <td>{{ $h->kaynak->getLabel() }}</td>
                    <td class="sag">{{ $h->tip === \App\Enums\CariTipi::Borc ? $para($h->tutar) : '' }}</td>
                    <td class="sag">{{ $h->tip === \App\Enums\CariTipi::Alacak ? $para($h->tutar) : '' }}</td>
                    <td class="sag {{ $sinif($satir['bakiye']) }}">{{ $para($satir['bakiye']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="color: #666; text-align: center;">Bu aralıkta hareket yok.</td></tr>
            @endforelse
            <tr class="toplam">
                <td colspan="3">Dönem toplamı</td>
                <td class="sag">{{ $para($ekstre['borc_toplam']) }}</td>
                <td class="sag">{{ $para($ekstre['alacak_toplam']) }}</td>
                <td></td>
            </tr>
            <tr class="kapanis">
                <td colspan="5">Kapanış bakiyesi</td>
                <td class="sag {{ $sinif($ekstre['kapanis']) }}">{{ $para($ekstre['kapanis']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="ibare">Bu belge mali değer taşımaz, bilgi amaçlıdır.</div>
</body>
</html>

<?php

namespace App\Http\Controllers;

use App\Models\Fis;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfBelgesi;
use Dompdf\Frame;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fiş çıktısı. Varsayılan 80 mm termal rulodur (fiş yazıcısı, sayfa 72 mm); ?boyut=a4 ile A4 şablonu açılır.
 */
class FisPdfController extends Controller
{
    /** 1 mm = 72 / 25,4 punto. */
    private const MM = 2.83465;

    /**
     * Sayfa genişliği (mm): 80 mm rulonun baskı alanı. Yazıcı sayfayı kâğıdın kenarından değil
     * baskı alanının başından basar ve kenarlarda kendi boşluğunu bırakır; sayfa 80 mm olursa
     * içerik sağa kayar ve sağ kenarı kesilir.
     */
    private const BASKI_GENISLIGI = 72;

    /** Ölçüm geçişindeki sayfa boyu (mm): fişin tamamı tek sayfaya sığacak kadar uzun. */
    private const OLCUM_BOYU = 5000;

    public function __invoke(Request $request, Fis $fis): Response
    {
        $fis->load(['kalemler.urun', 'musteri', 'user', 'iptalEden', 'cariHareketi', 'pesinTahsilat']);

        $veri = [
            'fis' => $fis,
            'firma' => config('firma'),
        ];

        $pdf = $request->query('boyut') === 'a4'
            ? Pdf::loadView('pdf.fis', $veri)->setPaper('a4')
            : $this->termal($veri);

        return $pdf->stream("fis-{$fis->fis_no}.pdf");
    }

    /**
     * Termal ruloda sayfa boyu fişin uzunluğu kadardır. dompdf boyu içeriğe göre ayarlayamadığı için
     * fiş önce çok uzun bir sayfaya dizilip yüksekliği ölçülür, sonra o boyda yeniden üretilir.
     *
     * @param  array<string, mixed>  $veri
     */
    private function termal(array $veri): PdfBelgesi
    {
        $genislik = self::BASKI_GENISLIGI * self::MM;
        $yukseklik = 0.0;

        $olcum = Pdf::loadView('pdf.fis-termal', $veri)
            ->setPaper([0, 0, $genislik, self::OLCUM_BOYU * self::MM]);

        $olcum->setCallbacks([[
            'event' => 'end_frame',
            'f' => function (Frame $frame) use (&$yukseklik): void {
                if (strtolower($frame->get_node()->nodeName) === 'body') {
                    $yukseklik += $frame->get_padding_box()['h'];
                }
            },
        ]]);

        $olcum->render();

        // Yuvarlama farkı son satırı ikinci sayfaya atmasın diye 1 mm pay bırakılır.
        return Pdf::loadView('pdf.fis-termal', $veri)
            ->setPaper([0, 0, $genislik, $yukseklik + self::MM]);
    }
}

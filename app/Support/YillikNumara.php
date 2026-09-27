<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Yıl bazlı sıralı belge numarası: YYYY-NNNNNN (fiş ve makbuz için).
 * Yılın satırı yoksa önce oluşturulur, sonra kilitlenip artırılır.
 * Transaction içinde çağrılmalıdır; işlem geri alınırsa numara da geri alınır (atlamaz).
 */
final class YillikNumara
{
    public static function al(string $tablo, int $yil): string
    {
        DB::table($tablo)->insertOrIgnore(['yil' => $yil, 'son_no' => 0]);

        $sonNo = (int) DB::table($tablo)->where('yil', $yil)->lockForUpdate()->value('son_no');
        $yeniNo = $sonNo + 1;

        DB::table($tablo)->where('yil', $yil)->update(['son_no' => $yeniNo]);

        return sprintf('%d-%06d', $yil, $yeniNo);
    }
}

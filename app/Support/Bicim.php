<?php

namespace App\Support;

use App\Enums\Birim;

/**
 * Sayıları Türkçe biçimde gösterir. Sadece gösterim içindir;
 * hesaplamalar bcmath ile yapılır, burada yapılmaz.
 */
final class Bicim
{
    /** Türk lirası simgesi. */
    private const TL = "\u{20BA}";

    /** 1250.5 -> "1.250,50 ₺" */
    public static function para(string|int|float|null $tutar): string
    {
        if ($tutar === null || $tutar === '') {
            return "\u{2014}";
        }

        return self::sayi($tutar) . ' ' . self::TL;
    }

    /**
     * Bakiyeyi yönüyle gösterir. Pozitif = müşteri borçlu.
     * 1250.5 -> "1.250,50 ₺ borç", -300 -> "300,00 ₺ alacak", 0 -> "0,00 ₺"
     */
    public static function bakiye(string|int|float|null $bakiye): string
    {
        $deger = bcadd((string) ($bakiye ?? '0'), '0', 2);
        $yon = bccomp($deger, '0', 2);

        if ($yon === 0) {
            return self::para('0');
        }

        return self::para(ltrim($deger, '-')) . ($yon > 0 ? ' borç' : ' alacak');
    }

    /** 12.5 + metre -> "12,50 m" */
    public static function miktar(string|int|float|null $miktar, ?Birim $birim = null): string
    {
        if ($miktar === null || $miktar === '') {
            return "\u{2014}";
        }

        return trim(self::sayi($miktar) . ' ' . $birim?->kisa());
    }

    /** 1250.5 -> "1.250,50" */
    public static function sayi(string|int|float $deger, int $basamak = 2): string
    {
        return number_format((float) $deger, $basamak, ',', '.');
    }
}

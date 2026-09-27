<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Para ve miktar değerleri için string tabanlı, bcmath ile çalışan yardımcılar.
 * Float kullanılmaz; tüm değerler "1250.50" biçiminde string taşınır.
 */
final class Sayi
{
    /** "12.5" → "12.50". Sayısal değilse veya ikiden fazla ondalığı varsa hata verir. */
    public static function dogrula(string $deger, string $alan, bool $sifirOlabilir = false): string
    {
        $deger = trim($deger);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $deger)) {
            throw new InvalidArgumentException("{$alan} sayısal olmalı ve en fazla iki ondalık basamak içermelidir (örn. 12.50).");
        }

        $deger = bcadd($deger, '0', 2);

        if (! $sifirOlabilir && bccomp($deger, '0', 2) <= 0) {
            throw new InvalidArgumentException("{$alan} sıfırdan büyük olmalıdır.");
        }

        return $deger;
    }

    /** Çarpar ve 2 basamağa yarım yukarı yuvarlar: 1.25 × 0.15 = 0.1875 → "0.19". */
    public static function carp(string $a, string $b): string
    {
        return self::yuvarla(bcmul($a, $b, 6), 2);
    }

    /** Yarım yukarı yuvarlama (bcmath keser, bu yüzden elle yapılır). */
    public static function yuvarla(string $deger, int $basamak = 2): string
    {
        $ek = '0.' . str_repeat('0', $basamak) . '5';

        return str_starts_with($deger, '-')
            ? bcsub($deger, $ek, $basamak)
            : bcadd($deger, $ek, $basamak);
    }

    /** Listeyi toplar; boşsa "0.00". */
    public static function topla(iterable $degerler): string
    {
        $toplam = '0.00';

        foreach ($degerler as $deger) {
            $toplam = bcadd($toplam, (string) $deger, 2);
        }

        return $toplam;
    }
}

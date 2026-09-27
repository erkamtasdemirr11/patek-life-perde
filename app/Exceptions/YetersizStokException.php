<?php

namespace App\Exceptions;

use App\Models\Urun;
use App\Support\Bicim;
use RuntimeException;

/**
 * Stok çıkışı istenen miktarı karşılamadığında fırlatılır.
 * Mesaj doğrudan kullanıcıya gösterilebilir:
 * "Stok yetersiz: Keten Beyaz için mevcut 12,50 m, istenen 20,00 m"
 */
class YetersizStokException extends RuntimeException
{
    public function __construct(
        public readonly Urun $urun,
        public readonly string $mevcut,
        public readonly string $istenen,
    ) {
        parent::__construct(sprintf(
            'Stok yetersiz: %s için mevcut %s, istenen %s',
            $urun->ad,
            Bicim::miktar($mevcut, $urun->birim),
            Bicim::miktar($istenen, $urun->birim),
        ));
    }

    /** Kaç birim eksik? */
    public function eksik(): string
    {
        return bcsub($this->istenen, $this->mevcut, 2);
    }
}

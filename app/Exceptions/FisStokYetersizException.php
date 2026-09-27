<?php

namespace App\Exceptions;

use App\Support\Bicim;
use RuntimeException;

/**
 * Fişteki bir veya daha fazla kalem için stok yetmediğinde fırlatılır.
 * Tüm eksikler tek seferde raporlanır; fiş hiç oluşturulmaz.
 */
class FisStokYetersizException extends RuntimeException
{
    /**
     * @param  list<YetersizStokException>  $eksikler
     */
    public function __construct(public readonly array $eksikler)
    {
        $satirlar = array_map(
            fn (YetersizStokException $e): string => sprintf(
                '• %s: mevcut %s, istenen %s (eksik %s)',
                $e->urun->ad,
                Bicim::miktar($e->mevcut, $e->urun->birim),
                Bicim::miktar($e->istenen, $e->urun->birim),
                Bicim::miktar($e->eksik(), $e->urun->birim),
            ),
            $eksikler,
        );

        parent::__construct("Stok yetersiz olduğu için fiş kaydedilmedi:\n" . implode("\n", $satirlar));
    }
}

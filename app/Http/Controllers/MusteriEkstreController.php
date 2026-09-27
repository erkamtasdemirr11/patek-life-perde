<?php

namespace App\Http\Controllers;

use App\Models\Musteri;
use App\Services\CariServisi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MusteriEkstreController extends Controller
{
    public function __invoke(Request $request, Musteri $musteri, CariServisi $cariServisi): Response
    {
        $veri = $request->validate([
            'baslangic' => ['nullable', 'date'],
            'bitis' => ['nullable', 'date'],
        ]);

        $baslangic = filled($veri['baslangic'] ?? null) ? $request->date('baslangic')->startOfDay() : null;
        $bitis = filled($veri['bitis'] ?? null) ? $request->date('bitis')->endOfDay() : null;

        return Pdf::loadView('pdf.ekstre', [
            'musteri' => $musteri,
            'ekstre' => $cariServisi->ekstre($musteri, $baslangic, $bitis),
            'firma' => config('firma'),
        ])
            ->setPaper('a4')
            ->stream('ekstre-' . $musteri->id . '.pdf');
    }
}

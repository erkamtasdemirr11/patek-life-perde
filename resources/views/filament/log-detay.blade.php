@php
    /** @var \Spatie\Activitylog\Models\Activity $kayit */
    $ozellikler = $kayit->properties->toArray();
    $yeni = $ozellikler['attributes'] ?? [];
    $eski = $ozellikler['old'] ?? [];
    $diger = collect($ozellikler)->except(['attributes', 'old']);
    $goster = function ($deger): string {
        if ($deger === null || $deger === '') return '—';
        if (is_bool($deger)) return $deger ? 'Evet' : 'Hayır';
        if (is_array($deger)) return json_encode($deger, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (string) $deger;
    };
@endphp

<div class="space-y-4 text-sm">
    @if ($yeni !== [])
        <table class="w-full">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <th class="py-1 pr-3">Alan</th>
                    @if ($eski !== [])<th class="py-1 pr-3">Eski</th>@endif
                    <th class="py-1">{{ $eski !== [] ? 'Yeni' : 'Değer' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($yeni as $alan => $deger)
                    <tr>
                        <td class="py-1.5 pr-3 font-medium">{{ $alan }}</td>
                        @if ($eski !== [])
                            <td class="py-1.5 pr-3 text-gray-500 line-through">{{ $goster($eski[$alan] ?? null) }}</td>
                        @endif
                        <td class="py-1.5">{{ $goster($deger) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($diger->isNotEmpty())
        <dl class="grid grid-cols-3 gap-x-3 gap-y-1">
            @foreach ($diger as $anahtar => $deger)
                <dt class="font-medium text-gray-600 dark:text-gray-300">{{ $anahtar }}</dt>
                <dd class="col-span-2">{{ $goster($deger) }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($yeni === [] && $diger->isEmpty())
        <p class="text-gray-500">Bu kayıtta ek ayrıntı yok.</p>
    @endif
</div>

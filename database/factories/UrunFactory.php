<?php

namespace Database\Factories;

use App\Enums\Birim;
use App\Models\Kategori;
use App\Models\Urun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Urun>
 */
class UrunFactory extends Factory
{
    protected $model = Urun::class;

    public function definition(): array
    {
        return [
            'ad' => ucfirst(fake()->words(2, true)),
            'kategori_id' => Kategori::factory(),
            'renk' => fake()->optional()->randomElement(['Beyaz', 'Krem', 'Gri', 'Bej', 'Antrasit', 'Lacivert']),
            'birim' => Birim::Metre,
            'alis_fiyati' => (string) fake()->randomFloat(2, 20, 200),
            'satis_fiyati' => (string) fake()->randomFloat(2, 50, 400),
            'kritik_stok' => '10.00',
            'aktif' => true,
        ];
    }

    public function adet(): static
    {
        return $this->state(['birim' => Birim::Adet]);
    }
}

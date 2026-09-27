<?php

namespace Database\Factories;

use App\Models\Musteri;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Musteri>
 */
class MusteriFactory extends Factory
{
    protected $model = Musteri::class;

    public function definition(): array
    {
        return [
            'firma_adi' => fake()->company(),
            'yetkili' => fake()->name(),
            'telefon' => fake()->phoneNumber(),
            'adres' => fake()->address(),
            'notlar' => null,
            'acilis_bakiyesi' => '0',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Kategori;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kategori>
 */
class KategoriFactory extends Factory
{
    protected $model = Kategori::class;

    public function definition(): array
    {
        return [
            'ad' => ucfirst(fake()->unique()->words(2, true)),
            'sira' => fake()->numberBetween(0, 20),
        ];
    }
}

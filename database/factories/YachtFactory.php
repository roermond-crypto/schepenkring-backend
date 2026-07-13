<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Yacht>
 */
class YachtFactory extends Factory
{
    /**
     * Define the model's default state — enough for a realistic OpenMarine
     * export preview (status is export-eligible so the only validation
     * error a generated test yacht shows is the intentional "test yachts
     * are excluded from publishing" one, not also a status/required-field
     * error unrelated to whatever's actually being tested).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boat_name' => fake()->words(3, true),
            'status' => 'approved',
            'boat_type' => fake()->randomElement(['motorboat', 'sailboat', 'catamaran']),
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('Model ###'),
            'year' => fake()->numberBetween(1990, 2026),
            'price' => fake()->numberBetween(20000, 500000),
            'new_or_used' => 'used',
            'loa' => fake()->randomFloat(2, 6, 20),
            'engine_manufacturer' => fake()->company(),
            'horse_power' => fake()->numberBetween(50, 800),
            'location_city' => 'Roermond',
            'location_country' => 'NL',
            'short_description_nl' => fake()->sentence(12),
            'is_test' => true,
        ];
    }
}

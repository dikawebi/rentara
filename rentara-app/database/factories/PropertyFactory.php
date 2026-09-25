<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Property;
use App\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'description' => fake()->sentence(),
            'full_address' => fake()->streetAddress().', Jakarta, Indonesia',
            'district' => fake()->randomElement(['Tebet', 'Cilandak', 'Menteng', 'Kelapa Gading']),
            'city' => 'Jakarta',
            'booking_expiry_days' => null,
        ];
    }
}

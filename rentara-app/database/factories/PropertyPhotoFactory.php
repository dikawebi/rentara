<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPhoto>
 */
class PropertyPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'uploaded_by' => User::factory(),
            'path' => fake()->uuid().'.webp',
            'thumbnail_path' => 'thumbnails/'.fake()->uuid().'.webp',
            'alt_text' => null,
            'position' => 0,
            'byte_size' => fake()->numberBetween(10000, 400000),
        ];
    }
}

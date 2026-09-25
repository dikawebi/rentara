<?php

namespace Database\Factories;

use App\ListingStatus;
use App\Models\Listing;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
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
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'status' => ListingStatus::Draft,
            'rejection_reason' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'published_at' => null,
        ];
    }
}

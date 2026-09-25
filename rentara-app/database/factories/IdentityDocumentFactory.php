<?php

namespace Database\Factories;

use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use App\Models\IdentityDocument;
use App\Models\RentalApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdentityDocument>
 */
class IdentityDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rental_application_id' => RentalApplication::factory(),
            'uploaded_by' => User::factory(),
            'document_type' => IdentityDocumentType::Ktp,
            'storage_path' => 'applications/'.Str::uuid().'/identity-documents/'.Str::uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => fake()->numberBetween(10000, 1000000),
            'review_status' => IdentityDocumentReviewStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'delete_after' => null,
        ];
    }
}

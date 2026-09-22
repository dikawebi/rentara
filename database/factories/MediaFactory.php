<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Property;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'property_id' => Property::factory(), 'unit_id' => null, 'disk' => 'local', 'path' => 'media/'.fake()->uuid().'.jpg', 'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 100, 'checksum' => hash('sha256', fake()->uuid()), 'caption' => null, 'sort_order' => 0, 'uploaded_by' => User::factory()];
    }
}

<?php

namespace Database\Factories;

use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffDocument> */
class StaffDocumentFactory extends Factory
{
    protected $model = StaffDocument::class;

    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'document_type' => 'other',
            'document_name' => 'Document',
            'file_path' => 'staff/test/document.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => User::factory(),
            'verification_status' => 'pending',
        ];
    }
}

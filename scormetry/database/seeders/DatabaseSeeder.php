<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::set('school_email_domain', 'example.com');

        User::factory()->admin()->approved()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->teacher()->approved()->create([
            'name' => 'Teacher 1',
            'email' => 'teacher1@example.com',
        ]);

        User::factory()->teacher()->approved()->create([
            'name' => 'Teacher 2',
            'email' => 'teacher2@example.com',
        ]);

        User::factory()->student()->approved()->create([
            'name' => 'Student 1',
            'email' => 'student1@example.com',
        ]);

        User::factory()->student()->approved()->create([
            'name' => 'Student 2',
            'email' => 'student2@example.com',
        ]);

        User::factory()->student()->approved()->create([
            'name' => 'Student 3',
            'email' => 'student3@example.com',
        ]);

        User::factory()->student()->pending()->create([
            'name' => 'Student 4',
            'email' => 'student4@otherdomain.com',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BoatFieldSeeder::class);
        $this->call(BoatFieldMappingSeeder::class);

        $location = Location::firstOrCreate([
            'code' => 'HQ',
        ], [
            'name' => 'Headquarters',
            'status' => 'ACTIVE',
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'type' => UserType::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'type' => UserType::CLIENT,
            'status' => UserStatus::ACTIVE,
            'client_location_id' => $location->id,
        ]);
    }
}

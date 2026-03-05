<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BoatType;

class BoatTypeSeeder extends Seeder
{
    public function run()
    {
        BoatType::firstOrCreate(['name' => 'Sailboat']);
        BoatType::firstOrCreate(['name' => 'Motorboat']);
        // Add more as needed
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['organizer', 'coach', 'fan', 'scorekeeper', 'admin'] as $key) {
            DB::table('roles')->updateOrInsert(
                ['key' => $key],
                ['name' => ucfirst($key), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}

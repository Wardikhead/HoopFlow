<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('organizations')->updateOrInsert(
            ['slug' => 'default'],
            ['name' => 'Default Organization', 'settings' => null, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

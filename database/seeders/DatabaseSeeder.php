<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Separates production bootstrap from development demo fixtures.
     */
    public function run(): void
    {
        // 1. Always run production bootstrap (roles, accounts, departments, counters, facilities, tariffs)
        $this->call(ProductionBootstrapSeeder::class);

        // 2. In non-production environments (local, testing), seed demo staff and sample members
        if (!app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        } else {
            $this->command?->info("Production environment: Skipping demo fixture seeding.");
        }
    }
}

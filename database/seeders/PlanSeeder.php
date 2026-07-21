<?php

namespace Database\Seeders;

use App\Services\SaaS\PlanCatalog;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        app(PlanCatalog::class)->seed();
    }
}

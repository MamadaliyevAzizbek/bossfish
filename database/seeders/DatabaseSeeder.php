<?php

namespace Database\Seeders;

use App\Models\ClientSubscription;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'ega@baliq.uz',
            'password' => 'Ega@2026!Strong',
            'password_plain' => 'Ega@2026!Strong',
            'role' => User::ROLE_OWNER,
            'phone' => null,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Venger Carp',
            'email' => 'hodim@vengercarp.uz',
            'password' => 'Venger@2026!Strong',
            'password_plain' => 'Venger@2026!Strong',
            'role' => User::ROLE_VENGER,
            'phone' => null,
            'is_active' => true,
        ]);

        // Test uchun tayyor direktor + xo‘jalik + hodim (login darhol ishlaydi)
        $director = User::create([
            'name' => 'Test Direktor',
            'email' => null,
            'phone' => '+998901234567',
            'password' => 'dir1234',
            'password_plain' => 'dir1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'created_by' => $owner->id,
            'access_expires_at' => now()->addYear()->endOfDay(),
        ]);

        ClientSubscription::create([
            'director_id' => $director->id,
            'farm_id' => null,
            'plan' => ClientSubscription::PLAN_CABINET,
            'billing_period' => ClientSubscription::PERIOD_YEAR,
            'price_amount' => ClientSubscription::defaultPriceForPlan(
                ClientSubscription::PLAN_CABINET,
                ClientSubscription::PERIOD_YEAR
            ),
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'next_check_at' => null,
            'check_interval_days' => 0,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'created_by' => $owner->id,
        ]);

        $farm = Farm::create([
            'name' => 'Test xo‘jalik',
            'region' => 'Toshkent viloyati',
            'director_id' => $director->id,
            'created_by' => $owner->id,
            'status' => 'active',
        ]);

        $worker = User::create([
            'name' => 'Test Hodim',
            'email' => null,
            'phone' => '+998907654321',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'role' => User::ROLE_WORKER,
            'is_active' => true,
            'created_by' => $owner->id,
            'director_id' => $director->id,
        ]);

        $worker->assignedFarms()->sync([$farm->id]);

        $this->call([
            CatalogProductSeeder::class,
            FeedProductSeeder::class,
        ]);
    }
}

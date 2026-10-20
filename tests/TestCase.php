<?php

namespace Tests;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array{0: User, 1: Farm, 2: Pond}
     */
    protected function createDirectorWithFarmAndPond(array $overrides = []): array
    {
        $director = User::create(array_merge([
            'name' => 'Direktor',
            'phone' => '+998901112233',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
        ], $overrides['director'] ?? []));

        $farm = Farm::create(array_merge([
            'name' => 'Test hojalik',
            'region' => 'Sirdaryo',
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ], $overrides['farm'] ?? []));

        $pond = Pond::create(array_merge([
            'farm_id' => $farm->id,
            'name' => 'Test havza',
            'region' => $farm->region,
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ], $overrides['pond'] ?? []));

        return [$director, $farm, $pond];
    }

    /** @deprecated Use createDirectorWithFarmAndPond() */
    protected function createDirectorWithBranchAndPond(array $overrides = []): array
    {
        if (isset($overrides['branch'])) {
            $overrides['farm'] = array_merge($overrides['branch'], $overrides['farm'] ?? []);
            unset($overrides['branch']);
        }

        return $this->createDirectorWithFarmAndPond($overrides);
    }
}

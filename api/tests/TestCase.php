<?php

namespace Tests;

use Database\Seeders\GovernmentTable2025Seeder;
use Database\Seeders\GovernmentTableSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Payroll\GovernmentScheduleTestFixturePublisher;

abstract class TestCase extends BaseTestCase
{
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder')
    {
        $result = parent::seed($class);
        $classes = is_array($class) ? $class : [$class];

        if (in_array('Database\\Seeders\\DatabaseSeeder', $classes, true)
            || in_array(GovernmentTableSeeder::class, $classes, true)
            || in_array(GovernmentTable2025Seeder::class, $classes, true)) {
            app(GovernmentScheduleTestFixturePublisher::class)->publishExistingSchedules();
        }

        return $result;
    }
}

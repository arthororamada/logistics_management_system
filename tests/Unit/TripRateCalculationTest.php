<?php

namespace Tests\Unit;

use App\Models\TripRate;
use PHPUnit\Framework\TestCase;

class TripRateCalculationTest extends TestCase
{
    public function test_rate_definitions_support_roles_present_in_configuration(): void
    {
        $rate = new TripRate;
        $rate->setRawAttributes(['driver_rate' => '1000.00', 'helper_rate' => '500.00', 'dispatch_assistant_rate' => '300.25', 'additional_dispatch_assistant_rate' => '25.10', 'minimum_drop' => 1]);
        $this->assertEquals(['Driver', 'Helper', 'Dispatch Assistant'], array_keys($rate->personnelRateDefinitions()));
        $this->assertEquals(30025, $rate->personnelSalaryCents('Dispatch Assistant', 1));
        $this->assertEquals(35045, $rate->personnelSalaryCents('Dispatch Assistant', 3));
        $this->assertEquals(50000, $rate->personnelSalaryCents('Helper', 3));
        $this->assertNull($rate->personnelSalaryCents('Unknown', 1));
        $this->assertEquals(35045, $rate->personnelSalaryCents('DISPATCH ASSISTANT', 3));
        $this->assertEquals(100000, $rate->personnelSalaryCents('DRIVER', 1));
        $this->assertEquals(['DRIVER' => $rate->personnelRateForDesignation('Driver'), 'Supervisor' => null], $rate->personnelRatesForDesignations(['DRIVER', 'Supervisor']));
    }
}

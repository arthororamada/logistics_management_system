<?php

namespace Tests\Feature;

use App\Models\TripRate;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TripRateManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_edit_link_is_valid_and_edit_page_opens(): void
    {
        $tripRate = TripRate::firstOrFail();
        $administrator = $this->administrator();

        $response = $this->actingAs($administrator)->getJson(route('trip-rates.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 15,
            'search' => ['value' => $tripRate->trip_number],
        ]));

        $response->assertOk();
        $actions = $response->json('data.0.actions');
        $this->assertStringContainsString('href="'.route('trip-rates.edit', $tripRate).'"', $actions);
        $this->assertStringNotContainsString('\\"', $actions);

        $this->actingAs($administrator)->get(route('trip-rates.edit', $tripRate))
            ->assertOk()
            ->assertSee('Edit Trip Rate')
            ->assertSee('value="'.$tripRate->trip_number.'"', false);
    }

    public function test_administrator_can_update_a_trip_rate(): void
    {
        $tripRate = TripRate::firstOrFail();
        $newDriverRate = (float) $tripRate->driver_rate + 25;

        $this->actingAs($this->administrator())->put(route('trip-rates.update', $tripRate), [
            'warehouse_code' => $tripRate->warehouse_code,
            'trip_type' => $tripRate->trip_type,
            'vehicle_group' => $tripRate->vehicle_group,
            'vehicle_type' => $tripRate->vehicle_type,
            'trip_number' => $tripRate->trip_number,
            'payment_type' => $tripRate->payment_type,
            'client_code' => $tripRate->client_code,
            'route_origin' => $tripRate->route_origin,
            'farthest_destination' => $tripRate->farthest_destination,
            'cargo_capacity' => $tripRate->cargo_capacity,
            'minimum_drop' => $tripRate->minimum_drop,
            'driver_rate' => $newDriverRate,
            'helper_rate' => $tripRate->helper_rate,
            'driver_cash_released_percentage' => $tripRate->driver_cash_released_percentage,
            'helper_cash_released_percentage' => $tripRate->helper_cash_released_percentage,
            'additional_driver_rate' => $tripRate->additional_driver_rate,
            'additional_helper_rate' => $tripRate->additional_helper_rate,
            'bypass_fuel_liters' => $tripRate->bypass_fuel_liters,
            'is_interisland' => (int) $tripRate->is_interisland,
            'is_with_tollfee' => (int) $tripRate->is_with_tollfee,
            'is_bypass_fuel_usage' => (int) $tripRate->is_bypass_fuel_usage,
            'is_active' => (int) $tripRate->is_active,
        ])->assertRedirect(route('trip-rates.index'));

        $this->assertDatabaseHas('trip_rates', [
            'id' => $tripRate->id,
            'driver_rate' => number_format($newDriverRate, 2, '.', ''),
            'updated_by' => $this->administrator()->id,
        ]);
    }

    public function test_edit_form_keeps_the_current_value_when_configuration_is_inactive(): void
    {
        $tripRate = TripRate::firstOrFail();
        VehicleType::where('vehicle_type', $tripRate->vehicle_type)->update(['is_active' => false]);

        $this->actingAs($this->administrator())->get(route('trip-rates.edit', $tripRate))
            ->assertOk()
            ->assertSee('value="'.$tripRate->vehicle_type.'" selected', false);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}

<?php

namespace Tests\Feature;

use App\Models\TripType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TripTypeManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_trip_types(): void
    {
        $this->actingAs($this->administrator())->get(route('trip-types.index'))->assertOk()->assertSee('Trip Type Directory');
        $response = $this->actingAs($this->administrator())->post(route('trip-types.store'), ['trip_type' => 'Delivery', 'is_active' => '1']);
        $response->assertRedirect(route('trip-types.index'));
        $this->assertDatabaseHas('trip_types', ['trip_type' => 'Delivery', 'is_active' => true]);
    }

    public function test_trip_type_must_be_unique(): void
    {
        $name = 'Test Trip '.str()->random(12);
        TripType::create(['trip_type' => $name, 'is_active' => true]);
        $this->actingAs($this->administrator())->post(route('trip-types.store'), ['trip_type' => $name, 'is_active' => '1'])->assertSessionHasErrors('trip_type');
    }

    public function test_administrator_can_update_and_delete_a_trip_type(): void
    {
        $tripType = TripType::create(['trip_type' => 'Pickup', 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('trip-types.update', $tripType), ['trip_type' => 'Return Pickup', 'is_active' => '0'])->assertRedirect(route('trip-types.index'));
        $this->assertDatabaseHas('trip_types', ['id' => $tripType->id, 'trip_type' => 'Return Pickup', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('trip-types.destroy', $tripType))->assertRedirect();
        $this->assertDatabaseMissing('trip_types', ['id' => $tripType->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}

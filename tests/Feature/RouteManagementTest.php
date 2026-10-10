<?php

namespace Tests\Feature;

use App\Models\Route as RouteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RouteManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_routes_directory_returns_datatables_json(): void
    {
        RouteRecord::create(['province' => 'Cebu', 'city' => 'Cebu City', 'brgy' => 'Lahug', 'longitued' => 123.89, 'latitude' => 10.33, 'is_active' => true]);

        $response = $this->actingAs($this->administrator())->getJson(route('routes.index', ['draw' => 1, 'start' => 0, 'length' => 25]));

        $response->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $response->assertJsonFragment(['province' => 'Cebu']);
    }

    public function test_administrator_can_create_update_and_delete_a_route(): void
    {
        $payload = ['province' => 'Metro Manila', 'city' => 'Manila', 'brgy' => 'Port Area', 'longitued' => '120.9842195', 'latitude' => '14.5995124', 'is_active' => '1'];
        $this->actingAs($this->administrator())->post(route('routes.store'), $payload)->assertRedirect(route('routes.index'));
        $route = RouteRecord::where('province', 'Metro Manila')->firstOrFail();

        $this->actingAs($this->administrator())->put(route('routes.update', $route), [...$payload, 'brgy' => 'Ermita', 'is_active' => '0'])->assertRedirect(route('routes.index'));
        $this->assertDatabaseHas('routes', ['id' => $route->id, 'brgy' => 'Ermita', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('routes.destroy', $route))->assertRedirect();
        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}

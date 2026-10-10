<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_clients(): void
    {
        $this->actingAs($this->administrator())->get(route('clients.index'))->assertOk()->assertSee('Client Directory');
        $this->actingAs($this->administrator())->get(route('clients.create'))
            ->assertOk()
            ->assertSee('name="api_key"', false)
            ->assertSee('name="token"', false)
            ->assertSee('name="endpoint"', false);

        $response = $this->actingAs($this->administrator())->post(route('clients.store'), [
            'client_code' => 'cli-001', 'client_name' => 'Acme Logistics', 'full_address' => '1 Main Street',
            'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Port Area',
            'email' => 'dispatch@acme.test', 'building_type' => 'Warehouse',
            'focal_person' => 'Maria Santos', 'contact_number' => '09171234567', 'api_key' => 'acme-key',
            'token' => 'acme-token', 'endpoint' => 'https://api.acme.test/v1', 'is_active' => '1',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'client_code' => 'CLI-001',
            'client_name' => 'Acme Logistics',
            'province' => 'Metro Manila',
            'city' => 'Manila',
            'barangay' => 'Port Area',
            'email' => 'dispatch@acme.test',
            'building_type' => 'Warehouse',
            'api_key' => 'acme-key',
            'token' => 'acme-token',
            'endpoint' => 'https://api.acme.test/v1',
            'is_active' => true,
        ]);
    }

    public function test_client_code_must_be_unique(): void
    {
        Client::create(['client_code' => 'CLI-002', 'client_name' => 'Existing Client', 'full_address' => 'Address', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => true]);

        $this->actingAs($this->administrator())->post(route('clients.store'), [
            'client_code' => 'CLI-002', 'client_name' => 'Duplicate', 'full_address' => 'Address', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => '1',
        ])->assertSessionHasErrors('client_code');
    }

    public function test_administrator_can_update_and_delete_a_client(): void
    {
        $client = Client::create(['client_code' => 'CLI-003', 'client_name' => 'Old Client', 'full_address' => 'Address', 'focal_person' => 'Contact', 'contact_number' => '123', 'is_active' => true]);

        $this->actingAs($this->administrator())->put(route('clients.update', $client), [
            'client_code' => 'CLI-004', 'client_name' => 'Updated Client', 'full_address' => 'New Address', 'focal_person' => 'New Contact', 'contact_number' => '456',
            'api_key' => 'updated-key', 'token' => 'updated-token', 'endpoint' => 'https://updated.test/api', 'is_active' => '0',
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'client_code' => 'CLI-004',
            'api_key' => 'updated-key',
            'token' => 'updated-token',
            'endpoint' => 'https://updated.test/api',
            'is_active' => false,
        ]);
        $this->actingAs($this->administrator())->delete(route('clients.destroy', $client))->assertRedirect();
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_client_endpoint_must_be_a_valid_url(): void
    {
        $this->actingAs($this->administrator())->post(route('clients.store'), [
            'client_code' => 'CLI-005', 'client_name' => 'API Client', 'full_address' => 'Address',
            'focal_person' => 'Contact', 'contact_number' => '123', 'endpoint' => 'not-a-url', 'is_active' => '1',
        ])->assertSessionHasErrors('endpoint');
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}

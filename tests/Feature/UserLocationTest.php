<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserLocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_create_a_user_with_a_location(): void
    {
        $location = $this->location('MNL-USER-01', 'Manila User Hub');
        $role = Role::where('slug', 'staff')->firstOrFail();
        $username = 'location.user.'.Str::lower(Str::random(6));

        $this->actingAs($this->administrator())->post(route('users.store'), [
            'name' => 'Location User',
            'username' => $username,
            'location_code' => $location->location_code,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'is_active' => true,
            'roles' => [$role->id],
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => $username,
            'location_code' => 'MNL-USER-01',
        ]);

        $this->actingAs($this->administrator())->get(route('users.index'))
            ->assertOk()
            ->assertSee('Manila User Hub')
            ->assertSee('MNL-USER-01');
    }

    public function test_a_deleted_location_cannot_be_assigned_to_a_user(): void
    {
        $location = $this->location('OLD-USER-01', 'Deleted User Hub');
        $location->update(['is_deleted' => now()]);
        $role = Role::where('slug', 'staff')->firstOrFail();

        $response = $this->actingAs($this->administrator())->post(route('users.store'), [
            'name' => 'Invalid Location User',
            'username' => 'invalid.location.'.Str::lower(Str::random(6)),
            'location_code' => $location->location_code,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'is_active' => true,
            'roles' => [$role->id],
        ]);

        $response->assertSessionHasErrors('location_code');
    }

    private function location(string $code, string $name): Location
    {
        return Location::create([
            'location_code' => $code,
            'location_name' => $name,
            'location_long' => '120.98421950',
            'location_lat' => '14.59951240',
        ]);
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}

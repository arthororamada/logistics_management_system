<?php

namespace Tests\Feature;

use App\Models\TripPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TripPaymentManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_administrator_can_view_and_create_trip_payments(): void
    {
        $this->actingAs($this->administrator())->get(route('trip-payments.index'))->assertOk()->assertSee('Trip Payment Directory');
        $response = $this->actingAs($this->administrator())->post(route('trip-payments.store'), ['payment_type' => 'Cash', 'is_active' => '1']);
        $response->assertRedirect(route('trip-payments.index'));
        $this->assertDatabaseHas('trip_payments', ['payment_type' => 'Cash', 'is_active' => true]);
    }

    public function test_payment_type_must_be_unique(): void
    {
        $name = 'Test Payment '.str()->random(10);
        TripPayment::create(['payment_type' => $name, 'is_active' => true]);
        $this->actingAs($this->administrator())->post(route('trip-payments.store'), ['payment_type' => $name, 'is_active' => '1'])->assertSessionHasErrors('payment_type');
    }

    public function test_administrator_can_update_and_delete_a_trip_payment(): void
    {
        $payment = TripPayment::create(['payment_type' => 'Bank Transfer', 'is_active' => true]);
        $this->actingAs($this->administrator())->put(route('trip-payments.update', $payment), ['payment_type' => 'Online Transfer', 'is_active' => '0'])->assertRedirect(route('trip-payments.index'));
        $this->assertDatabaseHas('trip_payments', ['id' => $payment->id, 'payment_type' => 'Online Transfer', 'is_active' => false]);
        $this->actingAs($this->administrator())->delete(route('trip-payments.destroy', $payment))->assertRedirect();
        $this->assertDatabaseMissing('trip_payments', ['id' => $payment->id]);
    }

    private function administrator(): User { return User::where('username', 'admin')->firstOrFail(); }
}

<?php

namespace Tests\Feature;

use App\Models\FuelSupplier;
use App\Models\OtherCost;
use App\Models\Personnel;
use App\Models\Role;
use App\Models\Route;
use App\Models\ShippingRate;
use App\Models\Trip;
use App\Models\TripPayment;
use App\Models\TripRate;
use App\Models\TripType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TripsManagementTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($this->admin);
    }

    public function test_creation_is_saved_immediately_and_retries_create_exactly_one_trip(): void
    {
        $key = (string) Str::uuid();
        $before = Trip::count();
        $this->post(route('trips.store'), ['creation_key' => $key])->assertRedirect();
        $trip = Trip::where('creation_key', $key)->firstOrFail();
        $this->post(route('trips.store'), ['creation_key' => $key])->assertRedirect(route('trips.show', $trip));
        $this->assertEquals($before + 1, Trip::count());
        $this->assertEquals('Open', $trip->current_status);
        $this->assertEquals($this->admin->id, $trip->created_by);
        $this->assertCount(1, $trip->history);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('Saved trip document')->assertSee($trip->trip_id);
    }

    public function test_saving_related_rows_updates_existing_trip_and_calculates_totals(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $trip->refresh();
        $this->assertEquals(1, Trip::where('creation_key', $trip->creation_key)->count());
        $this->assertEquals(100, $trip->total_distance);
        $this->assertEquals(25, $trip->total_liters_required);
        $this->assertEquals(600, $trip->fuel_price);
        $this->assertEquals(50, $trip->total_released);
        $this->assertEquals(1700, $trip->total_nonreleased);
        $this->assertEquals(1750, $trip->total_cost);
        $this->assertEquals(1100, $trip->personnels->first()->salary);
        $this->assertEquals(Vehicle::find($payload['vehicle_id'])->body_number, $trip->van_number);
        $this->assertEquals(1, $trip->version);
        foreach (['personnels', 'segments', 'fuelPurchases', 'costs'] as $relation) {
            $this->assertEquals($trip->trip_id, $trip->$relation->first()->trip_id);
        }
        $payload['version'] = $trip->version;
        foreach (['personnels' => 'personnels', 'segments' => 'segments', 'fuel_purchases' => 'fuelPurchases', 'costs' => 'costs'] as $key => $relation) {
            $payload[$key][0]['id'] = $trip->$relation->first()->id;
        }
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1, $trip->personnels()->count());
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'] = [];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(0, $trip->costs()->count());
        $this->assertEquals(1700, $trip->fresh()->total_cost);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('TEST-VAN')->assertSee('1,100.00');
        $this->postJson(route('trips.dispatch', $trip))->assertOk();
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('TEST-VAN')->assertDontSee('Primary navigation')->assertDontSee('Financial summary');
    }

    public function test_dispatch_saves_pending_changes_and_completion_is_recorded_once(): void
    {
        $trip = $this->draft();
        $this->postJson(route('trips.dispatch', $trip), $this->payload() + ['reason' => 'Ready'])->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertDatabaseHas('trip_status', ['trip_id' => $trip->trip_id, 'status' => 'Dispatched', 'reason' => 'Ready', 'created_by' => $this->admin->id]);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-readonly')->assertDontSee('id="trip-form"', false)->assertSee('Print Trip');
        $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
        $this->putJson(route('trips.update', $trip), $this->payload())->assertStatus(409);
        $this->postJson(route('trips.complete', $trip), ['reason' => 'Delivered'])->assertOk();
        $trip->refresh();
        $this->assertEquals('Completed', $trip->current_status);
        $this->assertNotNull($trip->date_completed);
        $this->assertEquals($this->admin->id, $trip->updated_by);
        $this->postJson(route('trips.complete', $trip))->assertStatus(409);
        $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
        $this->assertEquals(3, $trip->history()->count());
        $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('Complete Trip')->assertDontSee('Dispatch Truck');
        $this->get(route('trips.index', ['search' => $trip->trip_id]))->assertOk()->assertSee('Completed')->assertSee('Print Trip')->assertDontSee('Complete Trip');
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('Completed');
    }

    public function test_trip_badges_and_actions_follow_the_saved_status(): void
    {
        $trip = $this->draft();
        $directory = fn () => $this->get(route('trips.index', ['search' => $trip->trip_id]));
        $directory()->assertOk()->assertSee('trip-status-open')->assertSee('trip-action-edit')
            ->assertSee('trip-action-dispatch')->assertSee('data-trip-operation="dispatch"', false)
            ->assertDontSee('trip-action-complete')->assertDontSee('trip-action-print');
        $this->postJson(route('trips.dispatch', $trip), $this->payload())->assertOk();
        $directory()->assertOk()->assertSee('trip-status-dispatched')->assertSee('trip-action-view')
            ->assertSee('trip-action-complete')->assertSee('trip-action-print')
            ->assertSee('data-trip-operation="complete"', false)->assertDontSee('trip-action-dispatch');
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-status-dispatched')
            ->assertSee('trip-action-complete')->assertSee('trip-action-print');
        $this->postJson(route('trips.complete', $trip))->assertOk();
        $directory()->assertOk()->assertSee('trip-status-completed')->assertSee('trip-action-view')
            ->assertSee('trip-action-print')->assertDontSee('trip-action-complete')->assertDontSee('trip-action-edit');
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('trip-status-completed')->assertSee('trip-action-print');
    }

    public function test_invalid_dispatch_rolls_back_pending_changes_and_open_cannot_complete(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['personnels'] = [];
        $this->postJson(route('trips.dispatch', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
        $this->assertNull($trip->fresh()->vehicle_id);
        $this->assertEquals(0, $trip->segments()->count());
        $this->assertEquals(1, $trip->history()->count());
        $this->postJson(route('trips.complete', $trip))->assertStatus(409);
    }

    public function test_stale_updates_and_foreign_child_ids_are_rejected_without_partial_changes(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->putJson(route('trips.update', $trip), $payload)->assertStatus(409);
        $other = $this->draft();
        $payload['personnels'][0]['id'] = $trip->personnels()->first()->id;
        $this->putJson(route('trips.update', $other), $payload)->assertUnprocessable();
        $this->assertNull($other->fresh()->vehicle_id);
        $this->assertEquals(0, $other->personnels()->count());
    }

    public function test_authorization_restricts_other_users_trips_even_with_operation_permissions(): void
    {
        $trip = $this->draft();
        $user = User::create(['name' => 'Trip Operator', 'username' => 'operator-'.Str::random(8), 'password' => 'secret', 'is_active' => true]);
        $role = Role::create(['name' => 'Trip Operator', 'slug' => 'operator-'.Str::random(8)]);
        $role->permissions()->attach(\App\Models\Permission::whereIn('slug', ['trips.view', 'trips.create', 'trips.update', 'trips.dispatch', 'trips.complete'])->pluck('id'));
        $user->roles()->attach($role);
        $this->actingAs($user);
        $this->get(route('trips.show', $trip))->assertForbidden();
        $this->get(route('trips.print', $trip))->assertForbidden();
        $this->putJson(route('trips.update', $trip), $this->payload())->assertForbidden();
        $this->postJson(route('trips.dispatch', $trip))->assertForbidden();
        $this->postJson(route('trips.complete', $trip))->assertForbidden();
        $this->getJson(route('trips.index', ['search' => $trip->trip_id]))->assertOk()->assertJsonPath('total', 0);
        $this->post(route('trips.store'), ['creation_key' => $trip->creation_key])->assertForbidden();
    }

    public function test_references_money_and_tariff_compatibility_are_validated(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['vehicle_id'] = 999999999;
        $payload['fuel_purchases'][0]['liters_required'] = -1;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'fuel_purchases.0.liters_required']);
        $payload = $this->payload();
        TripRate::find($payload['tariff_id'])->update(['vehicle_group' => 'Different group']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('tariff_id');
        $this->assertEquals(0, $trip->personnels()->count());
    }

    public function test_shipping_tariffs_and_bypass_fuel_are_applied_by_backend(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        TripRate::find($payload['tariff_id'])->update(['is_bypass_fuel_usage' => true, 'bypass_fuel_liters' => 42]);
        $shipping = ShippingRate::create(['shipping_line_name' => 'Test Shipping', 'port_origin' => 'Manila', 'port_destination' => 'Cebu', 'vehicle_type' => 'Truck', 'shippine_rate' => 1234.56, 'is_active' => true]);
        $payload['costs'][0]['shipping_rate_id'] = $shipping->id;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(42, $trip->fresh()->total_liters_required);
        $this->assertEquals(1234.56, $trip->costs()->first()->released_amount);
    }

    public function test_guests_and_users_without_permissions_cannot_access_trips(): void
    {
        $trip = $this->draft();
        auth()->logout();
        $this->get(route('trips.index'))->assertRedirect(route('login'));
        $user = User::create(['name' => 'No permission', 'username' => 'noperms-'.Str::random(8), 'password' => 'secret', 'is_active' => true]);
        $this->actingAs($user)->get(route('trips.index'))->assertForbidden();
        $this->post(route('trips.store'), ['creation_key' => (string) Str::uuid()])->assertForbidden();
        $this->get(route('trips.show', $trip))->assertForbidden();
    }

    public function test_dispatched_document_keeps_saved_reference_names_and_tariff(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->postJson(route('trips.dispatch', $trip), $payload)->assertOk();
        TripRate::find($payload['tariff_id'])->update(['client_code' => 'CHANGED CLIENT']);
        Personnel::find($payload['personnels'][0]['employee_id'])->update(['first_name' => 'CHANGED NAME']);
        FuelSupplier::find($payload['fuel_purchases'][0]['fuel_supplier'])->update(['fuel_supplier_name' => 'CHANGED SUPPLIER']);
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('Test Driver')->assertDontSee('CHANGED CLIENT')->assertDontSee('CHANGED NAME')->assertDontSee('CHANGED SUPPLIER');
    }

    public function test_dispatch_rejects_inactive_references_without_changing_status(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        Personnel::find($payload['personnels'][0]['employee_id'])->update(['is_active' => false]);
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable();
        $this->assertEquals('Open', $trip->fresh()->current_status);
        $this->assertEquals(1, $trip->history()->count());
    }

    public function test_directory_search_filter_sort_and_pagination_use_backend_data(): void
    {
        $trip = $this->draft();
        $this->draft();
        $this->getJson(route('trips.index', ['search' => $trip->trip_id, 'current_status' => 'Open', 'sort' => 'trip_id', 'direction' => 'asc', 'per_page' => 25]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('per_page', 25)->assertJsonPath('data.0.trip_id', $trip->trip_id);
    }

    public function test_ajax_creation_retries_and_referenced_maintenance_deletion_are_handled(): void
    {
        $key = (string) Str::uuid();
        $this->postJson(route('trips.store'), ['creation_key' => $key])->assertOk()->assertJsonPath('trip.current_status', 'Open');
        $trip = Trip::where('creation_key', $key)->firstOrFail();
        $this->postJson(route('trips.store'), ['creation_key' => $key])->assertOk()->assertJsonPath('trip.id', $trip->id);
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->deleteJson(route('fuel-suppliers.destroy', $payload['fuel_purchases'][0]['fuel_supplier']))->assertStatus(409)->assertJsonPath('message', 'This record is used by a trip and cannot be deleted. Deactivate it instead where supported.');
        $this->assertDatabaseHas('fuel_suppliers', ['id' => $payload['fuel_purchases'][0]['fuel_supplier']]);
    }

    public function test_removing_and_readding_personnel_in_one_save_does_not_duplicate_assignments(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $payload['version'] = $trip->fresh()->version;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1, $trip->personnels()->count());
        $this->assertEquals(1, $trip->segments()->count());
    }

    public function test_details_form_has_requested_sections_and_fields_in_order(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('Print Trip')->assertSee('Trip Financial Summary')->assertDontSee('Status history');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//form[@id="trip-form"]/section[@data-trip-section]');
        $this->assertEquals(['general', 'vehicle', 'segments', 'personnels', 'fuel_purchases', 'costs', 'disbursements'], array_map(fn ($section) => $section->getAttribute('data-trip-section'), iterator_to_array($sections)));
        foreach ($sections as $section) {
            $this->assertStringContainsString('is-collapsed', $section->getAttribute('class'));
            $this->assertEquals(1, $xpath->query('.//div[@class="card-body" and @hidden]', $section)->length);
            $this->assertEquals(1, $xpath->query('.//button[@data-card-toggle and @aria-expanded="false"]', $section)->length);
        }
        $generalNames = $xpath->query('//section[@data-trip-section="general"]//*[@name]');
        $this->assertEquals(['trip_no', 'drops_number', 'trip_type', 'payment_type', 'tariff_id'], array_map(fn ($input) => $input->getAttribute('name'), iterator_to_array($generalNames)));
        $personnelLabels = $xpath->query('//section[@data-trip-section="personnels"]//div[@data-trip-row][not(ancestor::template)]//label');
        $this->assertCount(3, $personnelLabels);
        $personnelNames = $xpath->query('//section[@data-trip-section="personnels"]//*[@name]');
        foreach ($personnelNames as $input) {
            $this->assertStringNotContainsString('cash_released', $input->getAttribute('name'));
        }
        $this->assertEquals(1, $xpath->query('//section[@data-trip-section="disbursements"]//input[@name="gcash_number"]')->length);
        $this->assertEquals(1, $xpath->query('//section[@data-trip-section="general"]//select[@name="tariff_id"]')->length);
        $this->assertEquals(0, $xpath->query('//form[@id="trip-form"]//*[@name="trip_route_origin" or @name="trip_route_destination" or @name="trip_route_city"]')->length);
        $this->assertEquals(0, $xpath->query('//form[@id="trip-form"]//*[@name="reason"]')->length);
        $this->assertEquals(0, $xpath->query('//form[@id="trip-form"]//select[not(@data-trip-searchable)]')->length);
        $this->assertEquals(1, $xpath->query('//article[@class="trip-document"]/*[last()][@data-trip-financial-summary]')->length);
        $this->get(route('trips.print', $trip))->assertStatus(409);
    }

    public function test_designation_trip_rate_and_drop_changes_recalculate_salary_on_backend(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        // A supplied salary does not override the configured rate.
        $payload['personnels'][0]['salary'] = 1;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1100, $trip->personnels()->first()->salary);
        $this->assertEquals(0, $trip->personnels()->first()->cash_released);
        $this->assertEquals(1100, $trip->personnels()->first()->non_cash_released);
        $payload['version'] = $trip->fresh()->version;
        $payload['personnels'][0]['id'] = $trip->personnels()->first()->id;
        $payload['personnels'][0]['designation'] = 'Helper';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(550, $trip->personnels()->first()->salary);
        $this->assertEquals(550, $trip->personnels()->first()->non_cash_released);
        $rate = TripRate::find($payload['tariff_id'])->replicate();
        $rate->helper_rate = 700;
        $rate->additional_helper_rate = 75;
        $rate->save();
        $payload['version'] = $trip->fresh()->version;
        $payload['tariff_id'] = $rate->id;
        $payload['drops_number'] = 3;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(850, $trip->personnels()->first()->salary);
        $this->assertEquals($rate->id, $trip->fresh()->tripRate->id);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('data-personnel-rates', false)->assertSee('850.00');
        $payload['version'] = $trip->fresh()->version;
        $payload['personnels'][0]['designation'] = 'Coordinator';
        Personnel::find($payload['personnels'][0]['employee_id'])->update(['position' => 'Coordinator']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
    }

    public function test_disbursement_preserves_released_money_when_salary_changes(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $employeeId = $payload['personnels'][0]['employee_id'];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $payload['version'] = $trip->fresh()->version;
        $payload['personnels'][0]['id'] = $trip->personnels()->first()->id;
        $payload['disbursements'] = [['employee_id' => $employeeId, 'cash_released' => 200]];
        $payload['gcash_number'] = '09123456789';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(200, $trip->personnels()->first()->cash_released);
        $this->assertEquals(900, $trip->personnels()->first()->non_cash_released);
        $this->assertEquals('09123456789', $trip->fresh()->gcash_number);
        $payload['version'] = $trip->fresh()->version;
        $payload['personnels'][0]['designation'] = 'Helper';
        unset($payload['disbursements']);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(200, $trip->personnels()->first()->cash_released);
        $this->assertEquals(350, $trip->personnels()->first()->non_cash_released);
        $payload['version'] = $trip->fresh()->version;
        $payload['disbursements'] = [['employee_id' => $employeeId, 'cash_released' => 100]];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('disbursements');
        unset($payload['disbursements']);
        TripRate::find($payload['tariff_id'])->update(['helper_rate' => 100, 'additional_helper_rate' => 0]);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('disbursements');
        $this->assertEquals(200, $trip->personnels()->first()->cash_released);
        $this->assertEquals(550, $trip->personnels()->first()->salary);
        $payload['personnels'] = [];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
        $this->assertEquals(1, $trip->personnels()->count());
    }

    public function test_disbursements_cannot_bypass_personnel_or_exceed_salary(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['personnels'][0]['cash_released'] = 200;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels.0.cash_released');
        unset($payload['personnels'][0]['cash_released']);
        $payload['disbursements'] = [['employee_id' => $payload['personnels'][0]['employee_id'], 'cash_released' => 1200]];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('disbursements');
        $payload['personnels'] = [];
        $payload['disbursements'][0]['cash_released'] = 100;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('disbursements');
        $this->assertEquals(0, $trip->personnels()->count());
        $this->assertEquals(0, $trip->fresh()->version);
    }

    public function test_route_segments_are_authoritative_for_trip_origin_destination_and_distance(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $destination = Route::create(['province' => 'Batangas', 'city' => 'Lipa', 'brgy' => 'Test', 'longitued' => 121, 'latitude' => 14, 'is_active' => true]);
        $payload['segments'][] = ['origin_id' => $payload['segments'][0]['destination_id'], 'destination_id' => $destination->id, 'total_distance' => 55.25];
        $payload['trip_route_origin'] = 'Do not use supplied value';
        $payload['trip_route_destination'] = 'Do not use supplied value';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals('Manila', $trip->fresh()->trip_route_origin);
        $this->assertEquals('Lipa', $trip->fresh()->trip_route_destination);
        $this->assertEquals(155.25, $trip->fresh()->total_distance);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('Lipa')->assertSee('155.25');
    }

    private function draft(): Trip
    {
        $key = (string) Str::uuid();
        $this->post(route('trips.store'), ['creation_key' => $key])->assertRedirect();

        return Trip::where('creation_key', $key)->firstOrFail();
    }

    public function test_personnel_position_options_follow_personnel_records_and_unknown_rates_are_rejected(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $employee = Personnel::find($payload['personnels'][0]['employee_id']);
        $position = 'Coordinator '.Str::random(8);
        $employee->update(['position' => $position]);
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertSee($position);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $options = $xpath->query('//select[contains(@name,"[designation]")]/option');
        $this->assertContains($position, array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options)));
        $payload['personnels'][0]['designation'] = $position;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
        $payload['personnels'][0]['designation'] = 'Position not in Personnel';
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels.0.designation');
        $employee->update(['position' => 'DRIVER']);
        $payload['personnels'][0]['designation'] = 'DRIVER';
        $this->postJson(route('trips.dispatch', $trip), $payload)->assertOk();
        $this->assertEquals(1100, $trip->personnels()->first()->salary);
        $this->assertEquals(1100, $trip->personnels()->first()->non_cash_released);
        $this->assertDatabaseHas('trip_status', ['trip_id' => $trip->trip_id, 'status' => 'Dispatched', 'reason' => null]);
    }

    public function test_document_financial_summary_uses_saved_backend_totals_in_every_status(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $summary = app(\App\Services\TripService::class)->financialSummary($trip->fresh());
        $this->assertEquals(['Total personnel salary' => 1100, 'Total fuel cost' => 600, 'Total additional trip costs' => 50, 'Total released amount' => 50, 'Total non-released amount' => 1700, 'Total trip cost' => 1750], $summary);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('Trip Financial Summary')->assertSee('PHP 1,750.00');
        $this->postJson(route('trips.dispatch', $trip))->assertOk();
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('Trip Financial Summary')->assertSee('PHP 1,750.00')->assertDontSee('Primary navigation');
        $this->postJson(route('trips.complete', $trip))->assertOk();
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('Trip Financial Summary')->assertSee('Print Trip')->assertDontSee('id="trip-form"', false);
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('PHP 1,750.00');
        $response = $this->get(route('trips.index'));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertEquals(0, (new \DOMXPath($document))->query('//select[not(@data-trip-searchable)]')->length);
    }

    private function payload(): array
    {
        $suffix = Str::random(8);
        $type = TripType::create(['trip_type' => 'Delivery '.$suffix, 'is_active' => true]);
        $payment = TripPayment::create(['payment_type' => 'Cash '.$suffix, 'is_active' => true]);
        $vehicle = Vehicle::create(['warehouse_code' => 'TEST', 'vehicle_group' => 'Truck', 'body_number' => 'TEST-VAN-'.$suffix, 'vehicle_type' => 'Truck', 'plate_number' => 'TEST-'.$suffix, 'vehicle_make' => 'Isuzu', 'year_model' => 2024, 'engine_number' => 'ENG', 'chassi_number' => 'CHS', 'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'fuel_capacity' => 100, 'fuel_usage_per_kilometer' => 0.25, 'engine_type' => 'Diesel', 'cargo_capacity' => 5000, 'vehicle_status' => 'Available']);
        $employee = Personnel::create(['employee_id' => 'EMP-'.$suffix, 'last_name' => 'Driver', 'first_name' => 'Test', 'address' => 'Manila', 'contact_number' => '123', 'position' => 'Driver', 'personnel_type' => 'Driver', 'warehouse_code' => 'TEST', 'is_active' => true]);
        $helper = $employee->replicate();
        $helper->employee_id = 'HELP-'.$suffix;
        $helper->position = 'Helper';
        $helper->save();
        $origin = Route::create(['province' => 'Metro Manila', 'city' => 'Manila', 'brgy' => 'Origin', 'longitued' => 120, 'latitude' => 14, 'is_active' => true]);
        $destination = Route::create(['province' => 'Laguna', 'city' => 'Calamba', 'brgy' => 'Destination', 'longitued' => 121, 'latitude' => 14, 'is_active' => true]);
        $supplier = FuelSupplier::create(['fuel_supplier_name' => 'Supplier '.$suffix, 'price_per_liter' => 60, 'is_active' => true]);
        $cost = OtherCost::create(['cost_code' => 'C-'.$suffix, 'cost_name' => 'Toll', 'is_cash_release' => true, 'is_active' => true]);
        $tariff = TripRate::create(['warehouse_code' => 'TEST', 'trip_type' => $type->trip_type, 'vehicle_group' => 'Truck', 'vehicle_type' => 'Truck', 'trip_number' => 'RATE-'.$suffix, 'payment_type' => $payment->payment_type, 'client_code' => 'TEST', 'route_origin' => 'Manila', 'farthest_destination' => 'Calamba', 'cargo_capacity' => 5000, 'minimum_drop' => 1, 'driver_rate' => 1000, 'helper_rate' => 500, 'driver_cash_released_percentage' => 60, 'helper_cash_released_percentage' => 50, 'additional_driver_rate' => 100, 'additional_helper_rate' => 50, 'bypass_fuel_liters' => 0, 'is_active' => true]);

        return ['version' => 0, 'vehicle_id' => $vehicle->id, 'tariff_id' => $tariff->id, 'trip_no' => 'T-'.$suffix, 'trip_type' => $type->trip_type, 'payment_type' => $payment->payment_type, 'drops_number' => 2, 'trip_route_city' => 'Manila', 'trip_route_origin' => 'Manila', 'trip_route_destination' => 'Calamba', 'personnels' => [['employee_id' => $employee->id, 'designation' => 'Driver', 'salary' => 0]], 'segments' => [['origin_id' => $origin->id, 'destination_id' => $destination->id, 'total_distance' => 100]], 'fuel_purchases' => [['fuel_supplier' => $supplier->id, 'liters_required' => 10, 'price_per_liter' => 60]], 'costs' => [['cost_code' => $cost->id, 'costgroup' => 'Road', 'is_cash_released' => 1, 'released_amount' => 50, 'nonreleased_amount' => 0]]];
    }
}

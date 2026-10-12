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

    public function test_trip_number_dropdown_saves_each_option_and_restores_the_selection(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $response = $this->get(route('trips.show', $trip))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $options = $xpath->query('//select[@name="trip_no" and @required]/option');
        $this->assertEquals(['', '1', '2', '3', '4', '5'], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options)));
        $this->assertEquals('Select Trip Number', $options->item(0)->textContent);
        $this->assertEquals(0, $xpath->query('//input[@name="trip_no"]')->length);
        foreach (range(1, 5) as $number) {
            $payload['trip_no'] = $number % 2 ? (string) $number : $number;
            $payload['version'] = $trip->fresh()->version;
            $this->putJson(route('trips.update', $trip), $payload)->assertOk();
            $this->assertSame((string) $number, $trip->fresh()->trip_no);
            $this->assertSame('Open', $trip->fresh()->current_status);
            @$document->loadHTML($this->get(route('trips.show', $trip))->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertEquals((string) $number, $xpath->query('//select[@name="trip_no"]/option[@selected]')->item(0)->getAttribute('value'));
        }
    }

    public function test_missing_or_invalid_trip_numbers_cannot_save_new_or_existing_trips(): void
    {
        $payload = $this->payload();
        foreach ([false, true] as $saved) {
            $trip = $this->draft();
            if ($saved) {
                $this->putJson(route('trips.update', $trip), $payload)->assertOk();
            }
            $payload['version'] = $trip->fresh()->version;
            $before = $trip->fresh()->trip_no;
            foreach ([null, '', ' ', 0, 6, -1, '1.5', '01', 'abc', ['1']] as $number) {
                $invalid = array_replace($payload, ['trip_no' => $number]);
                $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()
                    ->assertJsonValidationErrors('trip_no')
                    ->assertJsonPath('sections.general', 'General Trip Information is required. Please complete all required fields.');
                $this->assertSame($before, $trip->fresh()->trip_no);
                $this->assertEquals($payload['version'], $trip->fresh()->version);
            }
            $invalid = $payload;
            unset($invalid['trip_no']);
            $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()->assertJsonValidationErrors('trip_no');
            $payload['version'] = 0;
        }
    }

    public function test_dispatch_rejects_legacy_trip_numbers_outside_the_dropdown(): void
    {
        $trip = $this->draft();
        $this->putJson(route('trips.update', $trip), $this->payload())->assertOk();
        $trip->refresh()->update(['trip_no' => 'LEGACY']);
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()
            ->assertJsonValidationErrors('trip_no')
            ->assertJsonPath('errors.trip_no.0', 'Please select a trip number from 1 to 5.');
        $this->assertSame('Open', $trip->fresh()->current_status);
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

    public function test_separate_save_and_dispatch_and_completion_are_recorded_once(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $vehicle = Vehicle::findOrFail($payload['vehicle_id']);
        $startingOdometer = (float) $vehicle->odometer;
        $this->saveAndDispatch($trip, $payload + ['reason' => 'Ready'])->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertEquals($startingOdometer, (float) $vehicle->fresh()->odometer);
        $this->assertDatabaseHas('trip_status', ['trip_id' => $trip->trip_id, 'status' => 'Dispatched', 'reason' => 'Ready', 'created_by' => $this->admin->id]);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('trip-readonly')->assertDontSee('id="trip-form"', false)->assertSee('Print Trip');
        $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
        $this->putJson(route('trips.update', $trip), $this->payload())->assertStatus(409);
        $this->postJson(route('trips.complete', $trip), ['reason' => 'Delivered'])->assertOk();
        $trip->refresh();
        $this->assertEquals('Completed', $trip->current_status);
        $this->assertNotNull($trip->date_completed);
        $this->assertEquals($this->admin->id, $trip->updated_by);
        $this->assertEquals($startingOdometer + (float) $trip->total_distance, (float) $vehicle->fresh()->odometer);
        $this->postJson(route('trips.complete', $trip))->assertStatus(409);
        $this->assertEquals($startingOdometer + (float) $trip->total_distance, (float) $vehicle->fresh()->odometer);
        $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
        $this->assertEquals(3, $trip->history()->count());
        $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('Complete Trip')->assertDontSee('Dispatch Truck');
        $this->get(route('trips.index', ['search' => $trip->trip_id]))->assertOk()->assertSee('Completed')->assertSee('Print Trip')->assertDontSee('Complete Trip');
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('Completed');
    }

    public function test_reopened_trip_can_be_dispatched_again_without_losing_history(): void
    {
        $trip = $this->draft();
        $this->saveAndDispatch($trip, $this->payload() + ['reason' => 'First dispatch'])->assertOk();
        $this->putJson(route('trip-status-updaters.update'), ['trip_id' => $trip->trip_id, 'version' => $trip->fresh()->version, 'new_status' => 'Open', 'reason' => 'Reschedule'])->assertOk();
        $this->postJson(route('trips.dispatch', $trip), ['reason' => 'Second dispatch'])->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertEquals(['First dispatch', 'Second dispatch'], $trip->history()->where('status', 'Dispatched')->pluck('reason')->all());
    }

    public function test_trip_badges_and_actions_follow_the_saved_status(): void
    {
        $trip = $this->draft();
        $directory = fn () => $this->get(route('trips.index', ['search' => $trip->trip_id]));
        $directory()->assertOk()->assertSee('trip-status-open')->assertSee('trip-action-edit')
            ->assertSee('trip-action-dispatch')->assertSee('data-trip-operation="dispatch"', false)
            ->assertDontSee('trip-action-complete')->assertDontSee('trip-action-print');
        $this->saveAndDispatch($trip, $this->payload())->assertOk();
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

    public function test_cancelled_trips_hide_details_links_and_reject_direct_access_until_reopened(): void
    {
        $trip = $this->draft();
        $this->putJson(route('trip-status-updaters.update'), [
            'trip_id' => $trip->trip_id, 'version' => $trip->version,
            'new_status' => 'Cancelled', 'reason' => 'Cancel delivery',
        ])->assertOk();
        $this->get(route('trips.index', ['search' => $trip->trip_id]))->assertOk()
            ->assertSee($trip->trip_id)->assertSee('trip-status-cancelled')
            ->assertDontSee(route('trips.show', $trip), false)
            ->assertDontSee('View Trip')->assertDontSee('Edit Trip');
        $this->get(route('trips.show', $trip))->assertForbidden();
        $this->getJson(route('trips.show', $trip))->assertForbidden()
            ->assertJsonPath('message', 'Cancelled trips cannot be viewed.');
        $this->get(route('trips.print', $trip))->assertStatus(409);

        $this->putJson(route('trip-status-updaters.update'), [
            'trip_id' => $trip->trip_id, 'version' => $trip->fresh()->version,
            'new_status' => 'Open', 'reason' => 'Resume delivery',
        ])->assertOk();
        $this->get(route('trips.index', ['search' => $trip->trip_id]))->assertOk()
            ->assertSee(route('trips.show', $trip), false)->assertSee('Edit Trip');
        $this->get(route('trips.show', $trip))->assertOk();
        $this->getJson(route('trips.show', $trip))->assertOk();
    }

    public function test_invalid_save_leaves_draft_unchanged_and_open_cannot_complete(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['personnels'] = [];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
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
        $this->getJson(route('trips.availability', $trip))->assertForbidden();
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
        $this->saveAndDispatch($trip, $payload)->assertOk();
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
        $this->assertEquals(['general', 'vehicle', 'segments', 'personnels', 'waybills', 'costs', 'disbursements'], array_map(fn ($section) => $section->getAttribute('data-trip-section'), iterator_to_array($sections)));
        $this->assertEquals(0, $xpath->query('//*[@data-trip-collection="fuel_purchases" or @id="trip-template-fuel_purchases"]')->length);
        $this->assertEquals(1, $xpath->query('//section[@data-trip-section="costs"]//input[contains(@name,"[fuel_purchase_id]")]')->length);
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
        $driver = Personnel::findOrFail($payload['personnels'][0]['employee_id'])->replicate();
        $driver->employee_id = 'DRIVER-'.Str::random(8);
        $driver->save();
        $payload['personnels'][] = ['employee_id' => $driver->id, 'designation' => 'Driver'];
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
        $driver = Personnel::findOrFail($payload['personnels'][0]['employee_id'])->replicate();
        $driver->employee_id = 'DRIVER-'.Str::random(8);
        $driver->save();
        $payload['personnels'][] = ['employee_id' => $driver->id, 'designation' => 'Driver'];
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
        $this->assertEquals(2, $trip->personnels()->count());
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
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels');
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
        $this->saveAndDispatch($trip, $payload)->assertOk();
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

    private function costCode(string $code, bool $cash = false): OtherCost
    {
        $record = OtherCost::firstOrCreate(['cost_code' => $code], ['cost_name' => $code, 'is_cash_release' => $cash, 'is_active' => true]);
        $record->update(['is_cash_release' => $cash, 'is_active' => true]);
        return $record;
    }

    public function test_default_and_cash_roro_amounts_preserve_backend_release_metadata(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $code = $payload['costs'][0]['cost_code'];
        $payload['costs'] = [['cost_code' => $code, 'cost_amount' => 85.25, 'remarks' => 'Cost test']];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $this->assertEquals(85.25, $cost->released_amount);
        $cost->update(['reference_id' => 'BACKEND-REFERENCE', 'costgroup' => 'Road']);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0] += ['id' => $cost->id, 'reference_id' => 'FORGED'];
        $payload['costs'][0]['cost_amount'] = 90;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals('BACKEND-REFERENCE', $cost->fresh()->reference_id);
        $this->assertEquals('Road', $cost->fresh()->costgroup);
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('BACKEND-REFERENCE');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['released_amount', 'nonreleased_amount', 'reference_id', 'is_cash_released', 'costgroup'] as $field) {
            $this->assertEquals(0, $xpath->query('//section[@data-trip-section="costs"]//*[@name and contains(@name,"['.$field.']")]')->length);
        }
        OtherCost::find($code)->update(['is_cash_release' => false]);
        $payload['version'] = $trip->fresh()->version;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(90, $cost->fresh()->nonreleased_amount);
        $this->assertEquals(0, $cost->fresh()->released_amount);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['cost_code'] = $this->costCode('CASH_RORO', true)->id;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(90, $cost->fresh()->released_amount);
        $payload['version'] = $trip->fresh()->version;
        unset($payload['costs'][0]['cost_amount']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_amount');
    }

    public function test_cash_fuel_recalculates_and_persists_details_with_validation(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['costs'] = [['cost_code' => $this->costCode('CASH_FUEL', true)->id, 'cost_amount' => 1, 'liters_required' => 12.25, 'price_per_liter' => 61.35]];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $this->assertEquals(751.54, $cost->released_amount);
        $this->assertEquals(12.25, $cost->liters_required);
        $this->assertEquals(61.35, $cost->price_per_liter);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('751.54');
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['id'] = $cost->id;
        $payload['costs'][0]['liters_required'] = 10;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(613.50, $cost->fresh()->released_amount);
        $payload['version'] = $trip->fresh()->version;
        unset($payload['costs'][0]['price_per_liter']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.price_per_liter');
        $payload['costs'][0]['price_per_liter'] = -60;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.price_per_liter');
        $payload['costs'][0]['price_per_liter'] = 60;
        $payload['costs'][0]['liters_required'] = -1;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.liters_required');
        $payload['costs'][0]['liters_required'] = 999999.99;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_amount');
        $payload['costs'][0]['cost_code'] = $this->costCode('CASH_RORO', true)->id;
        $payload['costs'][0]['cost_amount'] = 70;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertNull($cost->fresh()->liters_required);
        $this->assertNull($cost->fresh()->price_per_liter);
        $this->assertEquals(70, $cost->fresh()->released_amount);
    }

    public function test_roro_requires_matching_line_route_and_uses_server_rate(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $code = $this->costCode('RORO_COST');
        $line = \App\Models\ShippingLine::create(['shipping_line_name' => 'Line '.Str::random(8), 'billing_address' => 'Manila', 'focal_person' => 'Test', 'contact_number' => '123', 'is_active' => true]);
        $other = $line->replicate();
        $other->shipping_line_name = 'Other '.Str::random(8);
        $other->save();
        $rate = ShippingRate::create(['shipping_line_name' => $line->shipping_line_name, 'port_origin' => 'Manila', 'port_destination' => 'Cebu', 'vehicle_type' => 'Truck', 'shippine_rate' => 1234.56, 'is_active' => true]);
        $otherRate = $rate->replicate();
        $otherRate->shipping_line_name = $other->shipping_line_name;
        $otherRate->save();
        $payload['costs'] = [['cost_code' => $code->id, 'cost_amount' => 1]];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.shipping_rate_id');
        $payload['costs'][0] += ['shipping_rate_id' => $rate->id, 'shipping_line_name' => $other->shipping_line_name];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.shipping_rate_id');
        $payload['costs'][0]['shipping_line_name'] = $line->shipping_line_name;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $this->assertEquals(1234.56, $cost->nonreleased_amount);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee($line->shipping_line_name)->assertSee('1234.56');
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['id'] = $cost->id;
        $rate->update(['shippine_rate' => 1500]);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1500, $cost->fresh()->nonreleased_amount);
        $payload['version'] = $trip->fresh()->version;
        $rate->update(['is_active' => false]);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.shipping_rate_id');
        $rate->update(['is_active' => true, 'vehicle_type' => 'Van']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs');
    }

    public function test_unified_fuel_costs_save_edit_convert_and_remove_without_duplication(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $ordinary = $payload['costs'][0]['cost_code'];
        $supplier = $payload['fuel_purchases'][0]['fuel_supplier'];
        $fuelCode = $this->costCode('FUEL_PURCHASE');
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['cost_code' => $fuelCode->id, 'fuel_supplier' => $supplier, 'liters_required' => 10, 'price_per_liter' => 60, 'cost_amount' => 1, 'remarks' => 'Supplier invoice']];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $record = $trip->fuelPurchases()->firstOrFail();
        $this->assertEquals(600, $record->fuel_price);
        $this->assertEquals('Supplier invoice', $record->remarks);
        $this->assertEquals(0, $trip->costs()->count());
        $this->assertEquals(1700, $trip->fresh()->total_cost);
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertSee('Supplier invoice')->assertDontSee('data-trip-section="fuel_purchases"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertEquals(1, (new \DOMXPath($document))->query('//section[@data-trip-section="costs"]//input[contains(@name,"[fuel_purchase_id]") and @value="'.$record->id.'"]')->length);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['fuel_purchase_id'] = $record->id;
        $payload['costs'][0]['liters_required'] = 12.25;
        $payload['costs'][0]['price_per_liter'] = 61.35;
        $payload['costs'][0]['remarks'] = 'Corrected invoice';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1, $trip->fuelPurchases()->count());
        $this->assertEquals(751.54, $record->fresh()->fuel_price);
        $this->assertEquals('Corrected invoice', $record->fresh()->remarks);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0] = ['cost_code' => $ordinary, 'fuel_purchase_id' => $record->id, 'cost_amount' => 70];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(0, $trip->fuelPurchases()->count());
        $this->assertEquals(1, $trip->costs()->count());
        $this->assertEquals(0, $trip->fresh()->fuel_price);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'] = [];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs');
        $this->assertEquals(1, $trip->costs()->count());
    }

    public function test_unified_costs_preserve_legacy_fuel_without_an_active_fuel_code(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        OtherCost::where('cost_code', 'FUEL_PURCHASE')->update(['is_active' => false]);
        $record = $trip->fuelPurchases()->firstOrFail();
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('FUEL_PURCHASE')->assertDontSee('data-trip-collection="fuel_purchases"', false);
        $payload['version'] = $trip->fresh()->version;
        $payload['fuel_purchases'] = [];
        $payload['costs'][] = ['fuel_purchase_id' => $record->id, 'cost_code' => null, 'fuel_supplier' => $record->fuel_supplier, 'liters_required' => 10, 'price_per_liter' => 60, 'cost_amount' => 1, 'remarks' => 'Legacy record'];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1, $trip->fuelPurchases()->count());
        $this->assertEquals(600, $record->fresh()->fuel_price);
        $this->assertEquals('Legacy record', $record->fresh()->remarks);
        $this->assertEquals(1750, $trip->fresh()->total_cost);
    }

    public function test_unified_costs_reject_foreign_and_duplicate_fuel_ids(): void
    {
        $trip = $this->draft();
        $otherTrip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $otherTrip), $payload)->assertOk();
        $record = $otherTrip->fuelPurchases()->firstOrFail();
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['fuel_purchase_id' => $record->id, 'fuel_supplier' => $record->fuel_supplier, 'liters_required' => 10, 'price_per_liter' => 60, 'cost_amount' => 600]];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.fuel_purchase_id');
        $this->assertEquals(600, $record->fresh()->fuel_price);
        $payload['version'] = $otherTrip->fresh()->version;
        $payload['costs'][] = $payload['costs'][0];
        $this->putJson(route('trips.update', $otherTrip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.fuel_purchase_id');
        array_pop($payload['costs']);
        $payload['fuel_purchases'] = [['id' => $record->id, 'fuel_supplier' => $record->fuel_supplier, 'liters_required' => 10, 'price_per_liter' => 60]];
        $this->putJson(route('trips.update', $otherTrip), $payload)->assertUnprocessable()->assertJsonValidationErrors('fuel_purchases');
    }

    public function test_cost_availability_follows_trip_permissions_and_inactive_codes_are_rejected(): void
    {
        $payload = $this->payload();
        $code = OtherCost::findOrFail($payload['costs'][0]['cost_code']);
        $role = Role::firstOrCreate(['slug' => 'trip-cost-editor', 'name' => 'Trip cost editor']);
        $role->permissions()->sync(\App\Models\Permission::whereIn('slug', ['trips.view', 'trips.create', 'trips.update'])->pluck('id'));
        $user = User::create(['name' => 'Trip editor', 'username' => 'cost-editor-'.Str::random(8), 'password' => 'password', 'is_active' => true]);
        $user->roles()->attach($role);
        $this->assertFalse($user->hasPermission('other-costs.view'));
        $this->assertTrue(OtherCost::availableTo($user)->whereKey($code)->exists());
        $this->actingAs($user);
        $trip = $this->draft();
        $this->get(route('trips.show', $trip))->assertOk()->assertSee($code->cost_code);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $code->update(['is_active' => false]);
        $response = $this->get(route('trips.show', $trip))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertEquals(0, (new \DOMXPath($document))->query('//select[@data-cost-code]/option[@value="'.$code->id.'"]')->length);
        $payload['version'] = $trip->fresh()->version;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_code');
        $role->permissions()->detach(\App\Models\Permission::where('slug', 'trips.update')->value('id'));
        $this->assertCount(0, OtherCost::availableTo($user)->get());
        $this->putJson(route('trips.update', $trip), $payload)->assertForbidden();
    }

    public function test_unified_fuel_saves_and_dispatches_with_snapshots_and_remarks(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $supplier = $payload['fuel_purchases'][0]['fuel_supplier'];
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['cost_code' => $this->costCode('FUEL_PURCHASE')->id, 'fuel_supplier' => $supplier, 'liters_required' => 10, 'price_per_liter' => 60, 'cost_amount' => 1, 'remarks' => 'Dispatch fuel']];
        $this->saveAndDispatch($trip, $payload)->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertEquals(600, $trip->fresh()->fuel_price);
        $this->assertEquals(1700, $trip->fresh()->total_cost);
        FuelSupplier::findOrFail($supplier)->update(['fuel_supplier_name' => 'Renamed supplier']);
        $this->get(route('trips.print', $trip))->assertOk()->assertSee('Dispatch fuel')->assertSee('Additional trip costs')->assertDontSee('Renamed supplier')->assertDontSee('<h3>Fuel purchases</h3>', false);
    }

    public function test_earlier_fuel_cost_records_keep_storage_and_cash_release_reference(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $code = $this->costCode('FUEL_PURCHASE');
        $cost = $trip->costs()->create(['cost_code' => $code->id, 'fuel_supplier' => $payload['fuel_purchases'][0]['fuel_supplier'], 'liters_required' => 10, 'price_per_liter' => 60, 'nonreleased_amount' => 600, 'released_amount' => 0, 'reference_id' => 'OLD-FUEL-REF', 'created_by' => $this->admin->id]);
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['id' => $cost->id, 'cost_code' => $code->id, 'fuel_supplier' => $cost->fuel_supplier, 'liters_required' => 12, 'price_per_liter' => 60, 'cost_amount' => 1, 'remarks' => 'Existing fuel cost']];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(0, $trip->fuelPurchases()->count());
        $this->assertEquals(1, $trip->costs()->count());
        $this->assertEquals(720, $cost->fresh()->nonreleased_amount);
        $this->assertEquals('OLD-FUEL-REF', $cost->fresh()->reference_id);
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('720.00')->assertDontSee('OLD-FUEL-REF');
    }

    public function test_converting_a_referenced_cost_to_supplier_fuel_cannot_lose_release_metadata(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $cost->update(['reference_id' => 'RELEASED-COST']);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'] = [['id' => $cost->id, 'cost_code' => $this->costCode('FUEL_PURCHASE')->id, 'fuel_supplier' => $payload['fuel_purchases'][0]['fuel_supplier'], 'liters_required' => 10, 'price_per_liter' => 60, 'cost_amount' => 600]];
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_code');
        $this->assertEquals('RELEASED-COST', $cost->fresh()->reference_id);
        $this->assertEquals(1, $trip->costs()->count());
        $this->assertEquals(1, $trip->fuelPurchases()->count());
    }

    public function test_roro_dropdowns_and_saved_amount_use_the_exact_shipping_rate_record(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $lineName = 'Rate-only '.Str::random(8);
        $otherLine = 'Other rate line '.Str::random(8);
        $noRates = \App\Models\ShippingLine::create(['shipping_line_name' => 'No rates '.Str::random(8), 'billing_address' => 'Manila', 'focal_person' => 'Test', 'contact_number' => '123', 'is_active' => true]);
        $first = ShippingRate::create(['shipping_line_name' => $lineName, 'port_origin' => 'Batangas', 'port_destination' => 'Calapan', 'vehicle_type' => 'Truck', 'shippine_rate' => 1000, 'is_active' => true]);
        $second = $first->replicate();
        $second->shippine_rate = 1500;
        $second->save();
        $van = $first->replicate();
        $van->vehicle_type = 'Van';
        $van->shippine_rate = 2000;
        $van->save();
        $other = $first->replicate();
        $other->shipping_line_name = $otherLine;
        $other->port_destination = 'Puerto Galera';
        $other->shippine_rate = 800;
        $other->save();
        $inactive = $first->replicate();
        $inactive->shipping_line_name = 'Inactive rate '.Str::random(8);
        $inactive->is_active = false;
        $inactive->save();
        $this->costCode('RORO_COST');
        $response = $this->get(route('trips.show', $trip))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $template = '//template[@id="trip-template-costs"]';
        $lineSelect = $template.'//select[contains(@name,"[shipping_line_name]")]';
        $routeSelect = $template.'//select[contains(@name,"[shipping_rate_id]")]';
        $this->assertEquals(1, $xpath->query($lineSelect.'/option[@value="'.$lineName.'"]')->length);
        $this->assertEquals(0, $xpath->query($lineSelect.'/option[@value="'.$noRates->shipping_line_name.'"]')->length);
        $this->assertEquals(0, $xpath->query($lineSelect.'/option[@value="'.$inactive->shipping_line_name.'"]')->length);
        $this->assertEquals(1, $xpath->query($routeSelect.'[@disabled and @data-trip-searchable]')->length);
        foreach ([$first, $second, $van] as $rate) {
            $option = $xpath->query($routeSelect.'/option[@value="'.$rate->id.'"]')->item(0);
            $this->assertNotNull($option);
            $this->assertEquals($lineName, $option->getAttribute('data-line'));
            $this->assertEquals($rate->shippine_rate, $option->getAttribute('data-amount'));
            $this->assertStringStartsWith('Batangas → Calapan', trim($option->textContent));
            $this->assertStringContainsString('#'.$rate->id, $option->textContent);
        }
        $this->assertEquals('Batangas → Puerto Galera', trim($xpath->query($routeSelect.'/option[@value="'.$other->id.'"]')->item(0)->textContent));
        $payload['costs'] = [['cost_code' => $this->costCode('RORO_COST')->id, 'shipping_line_name' => $lineName, 'shipping_rate_id' => $second->id, 'cost_amount' => 1, 'remarks' => 'RORO selected rate']];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $this->assertEquals($second->id, $cost->shipping_rate_id);
        $this->assertEquals(1500, $cost->nonreleased_amount);
        $response = $this->get(route('trips.show', $trip))->assertOk();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $editorRow = '//section[@data-trip-section="costs"]//div[@data-trip-row][not(ancestor::template)]';
        $this->assertEquals(1, $xpath->query($editorRow.'//select[contains(@name,"[shipping_line_name]")]/option[@value="'.$lineName.'" and @selected]')->length);
        $this->assertEquals(1, $xpath->query($editorRow.'//select[contains(@name,"[shipping_rate_id]")]/option[@value="'.$second->id.'" and @selected and not(@disabled)]')->length);
        $this->assertEquals(1, $xpath->query($editorRow.'//input[contains(@name,"[cost_amount]") and @readonly and @value="1500.00"]')->length);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['id'] = $cost->id;
        $payload['costs'][0]['shipping_line_name'] = $otherLine;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.shipping_rate_id');
        $payload['costs'][0]['shipping_rate_id'] = $other->id;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(1, $trip->costs()->count());
        $this->assertEquals($other->id, $cost->fresh()->shipping_rate_id);
        $this->assertEquals(800, $cost->fresh()->nonreleased_amount);
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['shipping_line_name'] = $noRates->shipping_line_name;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('costs.0.shipping_line_name');
    }

    public function test_nonrunning_vehicles_and_every_inactive_person_are_rejected_on_save_and_dispatch(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->postJson(route('trips.store'), ['creation_key' => (string) Str::uuid(), 'vehicle_id' => $payload['vehicle_id'], 'personnels' => $payload['personnels']])
            ->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'personnels']);
        foreach (['Available', 'Maintenance', 'Inactive'] as $status) {
            Vehicle::findOrFail($payload['vehicle_id'])->update(['vehicle_status' => $status]);
            $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
            $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        }
        Vehicle::findOrFail($payload['vehicle_id'])->update(['vehicle_status' => 'Running']);
        $helper = Personnel::where('employee_id', 'like', 'HELP-%')->latest('id')->firstOrFail();
        $payload['personnels'][] = ['employee_id' => $helper->id, 'designation' => 'Helper'];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $helper->update(['is_active' => false]);
        $payload['version'] = $trip->fresh()->version;
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('personnels.1.employee_id');
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()->assertJsonValidationErrors('personnels.1.employee_id');
        $this->assertEquals('Open', $trip->fresh()->current_status);
    }

    public function test_saved_draft_rechecks_vehicle_status_on_dispatch_and_disables_ineligible_options(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        Vehicle::findOrFail($payload['vehicle_id'])->update(['vehicle_status' => 'Maintenance']);
        Personnel::findOrFail($payload['personnels'][0]['employee_id'])->update(['is_active' => false]);
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'personnels.0.employee_id']);
        $response = $this->get(route('trips.show', $trip))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//select[@name="vehicle_id"]/option[@value="'.$payload['vehicle_id'].'" and @disabled]')->length);
        $this->assertEquals(1, $xpath->query('//select[@name="personnels[0][employee_id]"]/option[@value="'.$payload['personnels'][0]['employee_id'].'" and @disabled]')->length);
        $this->assertEquals('Open', $trip->fresh()->current_status);
    }

    public function test_dispatched_assignments_block_saves_reassignments_and_stale_draft_dispatches(): void
    {
        $first = $this->draft();
        $second = $this->draft();
        $payload = $this->payload();
        $this->putJson(route('trips.update', $first), $payload)->assertOk();
        $this->putJson(route('trips.update', $second), $payload)->assertOk();
        $this->postJson(route('trips.dispatch', $first))->assertOk();
        $payload['version'] = $second->fresh()->version;
        $this->putJson(route('trips.update', $second), $payload)->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'personnels.0.employee_id']);
        $this->postJson(route('trips.dispatch', $second))->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'personnels.0.employee_id']);
        $replacement = $this->payload();
        $replacement['personnels'] = $payload['personnels'];
        $replacement['version'] = $second->fresh()->version;
        $this->putJson(route('trips.update', $second), $replacement)->assertUnprocessable()->assertJsonValidationErrors('personnels.0.employee_id');
        $replacement['personnels'] = $this->payload()['personnels'];
        $replacement['vehicle_id'] = $payload['vehicle_id'];
        $this->putJson(route('trips.update', $second), $replacement)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->getJson(route('trips.availability', $second))->assertOk()
            ->assertJsonPath('vehicles.'.$payload['vehicle_id'], 'This vehicle is already assigned to a dispatched trip.')
            ->assertJsonPath('personnels.'.$payload['personnels'][0]['employee_id'], 'This person is already assigned to a dispatched trip.');
        $this->get(route('trips.show', $second))->assertOk()->assertSee('already assigned to a dispatched trip');
        $this->postJson(route('trips.complete', $first))->assertOk();
        $this->getJson(route('trips.availability', $second))->assertJsonPath('vehicles.'.$payload['vehicle_id'], null)
            ->assertJsonPath('personnels.'.$payload['personnels'][0]['employee_id'], null);
        $payload['waybills'] = $this->payload()['waybills'];
        $this->putJson(route('trips.update', $second), $payload)->assertOk();
        $this->postJson(route('trips.dispatch', $second))->assertOk();
    }

    public function test_cancellation_and_reopening_release_resources_but_status_is_still_checked(): void
    {
        foreach (['Cancelled', 'Open'] as $status) {
            $first = $this->draft();
            $second = $this->draft();
            $payload = $this->payload();
            $this->saveAndDispatch($first, $payload)->assertOk();
            $this->putJson(route('trip-status-updaters.update'), ['trip_id' => $first->trip_id, 'version' => $first->fresh()->version, 'new_status' => $status, 'reason' => 'Release resources'])->assertOk();
            Vehicle::findOrFail($payload['vehicle_id'])->update(['vehicle_status' => 'Maintenance']);
            $this->putJson(route('trips.update', $second), $payload)->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
            Vehicle::findOrFail($payload['vehicle_id'])->update(['vehicle_status' => 'Running']);
            $payload['waybills'] = $this->payload()['waybills'];
            $this->saveAndDispatch($second, $payload)->assertOk();
        }
    }

    public function test_empty_saved_draft_dispatch_returns_each_missing_section_once(): void
    {
        $trip = $this->draft();
        $response = $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable();
        $sections = $response->json('sections');
        $this->assertEquals(['general', 'vehicle', 'segments', 'personnels', 'waybills', 'costs'], array_keys($sections));
        $this->assertEquals(count($sections), count(array_unique($sections)));
        $this->assertEquals(implode("\n", $sections), $response->json('message'));
        $this->assertStringNotContainsString('Required before dispatch.', $response->getContent());
        $this->assertEquals('Additional Trip Cost is required.', $sections['costs']);
        $this->assertEquals('Open', $trip->fresh()->current_status);
    }

    public function test_incomplete_save_collects_all_sections_and_rolls_back(): void
    {
        $trip = $this->draft();
        $response = $this->putJson(route('trips.update', $trip), [
            'version' => 0, 'drops_number' => 0, 'personnels' => [], 'segments' => [], 'costs' => [[]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['trip_no', 'trip_type', 'payment_type', 'tariff_id', 'vehicle_id', 'segments', 'personnels', 'costs.0.cost_code', 'costs.0.cost_amount']);
        $sections = $response->json('sections');
        $this->assertEquals(['general', 'vehicle', 'segments', 'personnels', 'waybills', 'costs'], array_keys($sections));
        foreach ($sections as $message) {
            $this->assertEquals(1, substr_count($response->json('message'), $message));
        }
        $this->assertEquals(0, $trip->fresh()->version);
        $this->assertNull($trip->vehicle_id);
        $this->assertEquals(0, $trip->personnels()->count());
        $this->assertEquals(1, $trip->history()->count());
    }

    public function test_invalid_route_and_missing_driver_are_grouped_and_corrections_dispatch_with_costs(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $valid = $payload;
        $payload['segments'][0]['total_distance'] = 0;
        $payload['personnels'][0]['designation'] = 'Helper';
        $payload['personnels'][] = ['employee_id' => null, 'designation' => null];
        $payload['costs'] = [];
        $response = $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable();
        $this->assertEquals(['segments', 'personnels'], array_keys($response->json('sections')));
        $valid['fuel_purchases'] = [];
        $this->saveAndDispatch($trip, $valid)->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertEquals(1, $trip->costs()->count());
    }

    public function test_save_groups_incomplete_conditional_cost_details_with_other_missing_sections(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $fuel = OtherCost::firstOrCreate(['cost_code' => 'FUEL_PURCHASE'], ['cost_name' => 'Fuel', 'is_cash_release' => false, 'is_active' => true]);
        $roro = OtherCost::firstOrCreate(['cost_code' => 'RORO_COST'], ['cost_name' => 'Shipping', 'is_cash_release' => false, 'is_active' => true]);
        $fuel->update(['is_active' => true]);
        $roro->update(['is_active' => true]);
        $payload['vehicle_id'] = null;
        $payload['trip_no'] = null;
        $payload['costs'] = [['cost_code' => $fuel->id], ['cost_code' => $roro->id]];
        $response = $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['costs.0.fuel_supplier', 'costs.0.liters_required', 'costs.0.price_per_liter', 'costs.1.shipping_line_name', 'costs.1.shipping_rate_id']);
        $this->assertEquals(['general', 'vehicle', 'costs'], array_keys($response->json('sections')));
        $this->assertEquals(1, substr_count($response->json('message'), 'Additional Trip Cost is required.'));
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('novalidate', false)->assertDontSee('Save &amp; Dispatch', false);
    }

    public function test_incomplete_save_also_reports_resource_eligibility_sections(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->saveAndDispatch($trip, $payload)->assertOk();
        $other = $this->draft();
        $payload['trip_no'] = null;
        $payload['segments'] = [];
        $response = $this->putJson(route('trips.update', $other), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['trip_no', 'segments', 'vehicle_id', 'personnels.0.employee_id']);
        $this->assertEquals(['general', 'vehicle', 'segments', 'personnels'], array_keys($response->json('sections')));
        $this->assertEquals('Open', $other->fresh()->current_status);
    }

    public function test_save_preserves_open_status_and_dispatch_changes_only_saved_trip_status(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['current_status'] = 'Dispatched';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk()->assertJsonPath('trip.current_status', 'Open');
        $trip->refresh();
        $this->assertEquals(1, $trip->version);
        $this->assertNull($trip->date_completed);
        $this->assertEquals(['Open'], $trip->history()->pluck('status')->all());
        $payload['version'] = $trip->version;
        $payload['trip_no'] = '2';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk()->assertJsonPath('trip.current_status', 'Open');
        $trip->refresh();
        $savedPeople = $trip->personnels()->get()->toArray();
        $savedCosts = $trip->costs()->get()->toArray();
        $savedTotals = [$trip->total_distance, $trip->total_cost, $trip->total_released];
        $version = $trip->version;
        $this->postJson(route('trips.dispatch', $trip), ['version' => $version, 'reason' => 'Dispatch saved document'])->assertOk()->assertJsonPath('trip.current_status', 'Dispatched');
        $trip->refresh();
        $this->assertEquals('2', $trip->trip_no);
        $this->assertEquals($version + 1, $trip->version);
        $this->assertEquals($savedPeople, $trip->personnels()->get()->toArray());
        $this->assertEquals($savedCosts, $trip->costs()->get()->toArray());
        $this->assertEquals($savedTotals, [$trip->total_distance, $trip->total_cost, $trip->total_released]);
        $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
        $this->assertEquals(['Open', 'Dispatched'], $trip->history()->pluck('status')->all());
    }

    public function test_dispatch_rejects_assignment_payloads_and_stale_versions_without_saving(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $this->postJson(route('trips.dispatch', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors(['vehicle_id', 'personnels', 'segments']);
        $this->assertNull($trip->fresh()->vehicle_id);
        $this->assertEquals(0, $trip->version);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->postJson(route('trips.dispatch', $trip), ['version' => 0])->assertStatus(409);
        $this->assertEquals('Open', $trip->fresh()->current_status);
        $this->assertEquals(1, $trip->history()->count());
        $this->postJson(route('trips.dispatch', $trip), ['version' => $trip->fresh()->version])->assertOk();
    }

    public function test_save_and_dispatch_buttons_use_separate_forms_and_endpoints(): void
    {
        $trip = $this->draft();
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertDontSee('Save &amp; Dispatch', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//form[@id="trip-form" and @novalidate]/div[@class="form-actions"]/button[normalize-space()="Save"]')->length);
        $this->assertEquals(0, $xpath->query('//form[@id="trip-form"]//button[@value="dispatch"]')->length);
        $this->assertEquals(1, $xpath->query('//form[@data-trip-operation="dispatch" and @action="'.route('trips.dispatch', $trip).'"]//button[normalize-space()="Dispatch Truck"]')->length);
    }

    public function test_first_save_and_updates_require_cost_entries_and_valid_amounts_accepting_zero(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['cost_code' => $payload['costs'][0]['cost_code'], 'cost_amount' => 0]];
        foreach ([0, 1] as $version) {
            $payload['version'] = $version;
            foreach (['missing', null, '', []] as $value) {
                $invalid = $payload;
                if ($value === 'missing') unset($invalid['costs']);
                else $invalid['costs'] = $value;
                $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()->assertJsonValidationErrors('costs')
                    ->assertJsonPath('sections.costs', 'Additional Trip Cost is required.');
                $this->assertEquals($version, $trip->fresh()->version);
            }
            foreach (['missing', null, '', 'invalid', -1, 1000000, '0.001'] as $amount) {
                $invalid = $payload;
                if ($amount === 'missing') unset($invalid['costs'][0]['cost_amount']);
                else $invalid['costs'][0]['cost_amount'] = $amount;
                $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_amount')
                    ->assertJsonPath('sections.costs', 'Additional Trip Cost is required.');
                $this->assertEquals($version, $trip->fresh()->version);
                $this->assertEquals($version ? 1 : 0, $trip->costs()->count());
            }
            $this->putJson(route('trips.update', $trip), $payload)->assertOk()->assertJsonPath('trip.current_status', 'Open');
            $cost = $trip->costs()->firstOrFail();
            $this->assertEquals(0, $cost->released_amount + $cost->nonreleased_amount);
            $payload['costs'][0]['id'] = $cost->id;
        }
        $payload['version'] = $trip->fresh()->version;
        $payload['costs'][0]['cost_amount'] = '15.25';
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertEquals(15.25, $trip->costs()->first()->released_amount);
    }

    public function test_dispatch_requires_saved_costs_and_accepts_a_zero_amount(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $payload['fuel_purchases'] = [];
        $payload['costs'] = [['cost_code' => $payload['costs'][0]['cost_code'], 'cost_amount' => 0]];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $trip->costs()->delete(); // Simulate a legacy trip saved without any costs.
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()->assertJsonValidationErrors('costs')
            ->assertJsonPath('errors.costs.0', 'Additional Trip Cost is required.');
        $this->assertEquals('Open', $trip->fresh()->current_status);
        $this->assertEquals(1, $trip->history()->count());
        $payload['version'] = $trip->fresh()->version;
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $cost = $trip->costs()->firstOrFail();
        $cost->update(['released_amount' => -1]);
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()->assertJsonValidationErrors('costs.0.cost_amount');
        $cost->update(['released_amount' => 0]);
        $this->postJson(route('trips.dispatch', $trip))->assertOk();
        $this->assertEquals('Dispatched', $trip->fresh()->current_status);
        $this->assertEquals(0, $trip->costs()->first()->released_amount);
    }

    public function test_new_trip_form_starts_with_a_blank_required_cost_amount(): void
    {
        $response = $this->get(route('trips.show', $this->draft()))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//div[@data-trip-collection="costs"]//input[@name="costs[0][cost_amount]" and @required and @value=""]')->length);
        $this->assertEquals(1, $xpath->query('//template[@id="trip-template-costs"]//input[contains(@name,"[cost_amount]") and @required and @value=""]')->length);
    }

    public function test_waybill_manifests_are_filtered_and_dispatch_updates_only_the_trip_packages_for_each_type(): void
    {
        foreach (['Delivery' => \App\Models\Delivery::class, 'Pickup' => \App\Models\Pickup::class, 'Transfer' => \App\Models\Transfer::class, 'Return' => \App\Models\ReturnManifest::class] as $type => $model) {
            $trip = $this->draft();
            $payload = $this->payload();
            TripType::firstOrCreate(['trip_type' => $type], ['is_active' => true]);
            TripRate::findOrFail($payload['tariff_id'])->update(['trip_type' => $type]);
            $payload['trip_type'] = $type;
            $prefix = 'WB-'.Str::random(10);
            $clientData = ['client_code' => $prefix, 'client_name' => 'Waybill test client', 'prefix' => Str::random(5), 'is_active' => true,
                'address' => 'Test address', 'full_address' => 'Test address', 'focal_person' => 'Test contact', 'contact_number' => '123'];
            $clientId = \Illuminate\Support\Facades\DB::table('clients')->insertGetId(array_intersect_key($clientData,
                array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('clients'))));
            $numbers = [$prefix.'-1', $prefix.'-2', $prefix.'-3'];
            $manifest = $model::create(['waybill_numbers' => $numbers, 'created_by' => $this->admin->id]);
            $unrelated = $model::create(['waybill_numbers' => [$prefix.'-other'], 'created_by' => $this->admin->id]);
            $dispatched = $model::create(['waybill_numbers' => [$prefix.'-old'], 'status' => 'dispatched', 'created_by' => $this->admin->id]);
            $detailsTable = ['Delivery' => 'delivery_details', 'Pickup' => 'pickup_details', 'Transfer' => 'transfer_details', 'Return' => 'return_details'][$type];
            \Illuminate\Support\Facades\DB::table($detailsTable)->insert([
                ['dm' => $manifest->id, 'waybill_number' => $numbers[0], 'status' => 'cancelled'],
                ['dm' => $unrelated->id, 'waybill_number' => $prefix.'-other', 'status' => 'cancelled'],
            ]);
            $records = $this->getJson(route('trips.manifests', $trip, false).'?trip_type='.$type)->assertOk()->json();
            $ids = array_column($records, 'id');
            $this->assertContains($manifest->id, $ids);
            $this->assertNotContains($dispatched->id, $ids);
            $this->assertSame($numbers, collect($records)->firstWhere('id', $manifest->id)['waybill_numbers']);
            foreach (array_merge($numbers, [$prefix.'-other']) as $number) {
                \Illuminate\Support\Facades\DB::table('packages')->insert([
                    'waybill_number' => $number, 'package_id' => 'PKG-'.$number,
                    'buyer_name' => 'Test Buyer', 'buyer_full_address' => 'Test Address',
                    'package_status' => 'for_pickup',
                    'client_id' => $clientId, 'order_number' => $number, 'package_description' => 'Test package',
                    'package_size' => 'Small', 'package_length' => 1, 'package_width' => 1, 'package_height' => 1,
                    'package_weight' => 1, 'payment_type' => 'Cash', 'total_price' => 1, 'declared_value' => 1,
                    'buyer_mobile' => '123', 'buyer_province' => 'Test', 'buyer_city' => 'Test', 'buyer_barangay' => 'Test',
                ]);
            }
            $payload['waybills'] = array_map(fn ($number) => ['dm_id' => $manifest->id, 'waybill_number' => $number, 'drops_number' => 1, 'quantity' => 2, 'kilogram' => 3, 'cbm' => 0.25], $numbers);
            $this->putJson(route('trips.update', $trip), $payload)->assertOk();
            $this->assertCount(3, $trip->waybills()->get());
            $this->assertSame('pending', $manifest->fresh()->status);
            $this->assertDatabaseHas($detailsTable, ['dm' => $manifest->id, 'waybill_number' => $numbers[0], 'status' => 'cancelled']);
            $this->assertSame(1, \Illuminate\Support\Facades\DB::table($detailsTable)->where('dm', $manifest->id)->count());
            $this->get(route('trips.show', $trip))->assertOk()->assertSee('Waybill Information')->assertSee($numbers[2]);
            $this->postJson(route('trips.dispatch', $trip))->assertOk();
            $this->assertSame('dispatched', $manifest->fresh()->status);
            $this->assertSame('pending', $unrelated->fresh()->status);
            foreach ($numbers as $number) {
                $this->assertDatabaseHas('trip_details', ['trip_id' => $trip->trip_id, 'dm_id' => $manifest->id, 'waybill_number' => $number]);
                $this->assertDatabaseHas($detailsTable, ['dm' => $manifest->id, 'waybill_number' => $number, 'status' => 'dispatched']);
                $this->assertDatabaseHas('packages', ['waybill_number' => $number, 'package_status' => 'delivery_dispatched']);
                $this->assertDatabaseHas('status_logs', ['waybill_number' => $number, 'status' => 'delivery_dispatched']);
            }
            $this->assertDatabaseHas('packages', ['waybill_number' => $prefix.'-other', 'package_status' => 'for_pickup']);
            $this->assertSame(3, \Illuminate\Support\Facades\DB::table($detailsTable)->where('dm', $manifest->id)->count());
            $this->assertDatabaseHas($detailsTable, ['dm' => $unrelated->id, 'waybill_number' => $prefix.'-other', 'status' => 'cancelled']);
            $this->postJson(route('trips.dispatch', $trip))->assertStatus(409);
            $this->assertSame(3, \Illuminate\Support\Facades\DB::table($detailsTable)->where('dm', $manifest->id)->count());
            $this->get(route('trips.print', $trip))->assertOk()->assertSee($numbers[0])->assertSee($numbers[1])->assertSee($numbers[2]);
        }
    }

    public function test_waybill_fields_membership_and_pending_status_are_required_and_failures_roll_back(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        foreach (['dm_id', 'waybill_number', 'drops_number', 'quantity', 'kilogram', 'cbm'] as $field) {
            $invalid = $payload;
            unset($invalid['waybills'][0][$field]);
            $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()->assertJsonValidationErrors('waybills.0.'.$field);
        }
        $invalid = $payload;
        $invalid['waybills'][0]['waybill_number'] = 'WRONG-SOURCE';
        $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable()->assertJsonValidationErrors('waybills.0.waybill_number');
        $invalid = $payload;
        $invalid['waybills'][] = $invalid['waybills'][0];
        $this->putJson(route('trips.update', $trip), $invalid)->assertUnprocessable();
        $manifest = \App\Models\Delivery::findOrFail($payload['waybills'][0]['dm_id']);
        $manifest->update(['status' => 'completed']);
        $this->putJson(route('trips.update', $trip), $payload)->assertUnprocessable()->assertJsonValidationErrors('waybills.0.dm_id');
        $this->assertSame(0, $trip->waybills()->count());
        $this->assertSame(0, $trip->fresh()->version);
        $manifest->update(['status' => 'pending']);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $manifest->update(['status' => 'dispatched']);
        $this->postJson(route('trips.dispatch', $trip))->assertUnprocessable()->assertJsonValidationErrors('waybills.0.dm_id');
        $this->assertSame('Open', $trip->fresh()->current_status);
        $this->assertSame(1, $trip->history()->count());
    }

    public function test_waybill_rows_can_be_updated_and_removed_and_foreign_ids_cannot_be_saved(): void
    {
        $trip = $this->draft();
        $payload = $this->payload();
        $manifest = \App\Models\Delivery::findOrFail($payload['waybills'][0]['dm_id']);
        $manifest->update(['waybill_numbers' => [$payload['waybills'][0]['waybill_number'], 'REMOVE-ME']]);
        $payload['waybills'][] = array_replace($payload['waybills'][0], ['waybill_number' => 'REMOVE-ME']);
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $row = $trip->waybills()->first();
        $payload['version'] = $trip->fresh()->version;
        $payload['waybills'] = [array_replace($payload['waybills'][0], ['id' => $row->id, 'quantity' => 9])];
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();
        $this->assertSame(1, $trip->waybills()->count());
        $this->assertSame(9, $row->fresh()->quantity);
        $this->assertSame($this->admin->id, $row->fresh()->updated_by);
        $foreign = $this->draft();
        $payload['version'] = 0;
        $this->putJson(route('trips.update', $foreign), $payload)->assertUnprocessable()->assertJsonValidationErrors('waybills');
        $this->assertSame(0, $foreign->waybills()->count());
    }

    public function test_trip_details_schema_has_only_requested_columns(): void
    {
        $expected = ['id', 'trip_id', 'dm_id', 'waybill_number', 'quantity', 'kilogram', 'cbm', 'drops_number', 'created_by', 'updated_by', 'created_at', 'updated_at'];
        $actual = \Illuminate\Support\Facades\Schema::getColumnListing('trip_details');
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_pending_dm_selection_supports_local_delivery_and_lowercase_types(): void
    {
        $trip = $this->draft();
        foreach (['Local Delivery' => \App\Models\Delivery::class, 'pickup' => \App\Models\Pickup::class,
            'transfer' => \App\Models\Transfer::class, 'return' => \App\Models\ReturnManifest::class] as $type => $model) {
            $pending = $model::create(['waybill_numbers' => ['PENDING-'.Str::random(8)], 'created_by' => $this->admin->id]);
            $completed = $model::create(['waybill_numbers' => ['COMPLETED-'.Str::random(8)], 'status' => 'completed', 'created_by' => $this->admin->id]);
            $response = $this->getJson(route('trips.manifests', $trip).'?'.http_build_query(['trip_type' => $type]))->assertOk();
            $this->assertContains($pending->id, array_column($response->json(), 'id'));
            $this->assertNotContains($completed->id, array_column($response->json(), 'id'));
            $trip->update(['trip_type' => $type]);
            $page = $this->get(route('trips.show', $trip))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($page->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//select[@data-trip-dm]/option[@value="'.$pending->id.'"]')->length);
            $this->assertSame(0, $xpath->query('//select[@data-trip-dm]/option[@value="'.$completed->id.'"]')->length);
        }
    }

    private function saveAndDispatch(Trip $trip, array $payload)
    {
        $this->putJson(route('trips.update', $trip), $payload)->assertOk();

        return $this->postJson(route('trips.dispatch', $trip), ['version' => $trip->fresh()->version, 'reason' => $payload['reason'] ?? null]);
    }

    private function payload(): array
    {
        $suffix = Str::random(8);
        $type = TripType::firstOrCreate(['trip_type' => 'Delivery'], ['is_active' => true]);
        $payment = TripPayment::create(['payment_type' => 'Cash '.$suffix, 'is_active' => true]);
        $vehicle = Vehicle::create(['warehouse_code' => 'TEST', 'vehicle_group' => 'Truck', 'body_number' => 'TEST-VAN-'.$suffix, 'vehicle_type' => 'Truck', 'plate_number' => 'TEST-'.$suffix, 'vehicle_make' => 'Isuzu', 'year_model' => 2024, 'engine_number' => 'ENG', 'chassi_number' => 'CHS', 'transmission' => 'Manual', 'fuel_type' => 'Diesel', 'fuel_capacity' => 100, 'fuel_usage_per_kilometer' => 0.25, 'engine_type' => 'Diesel', 'cargo_capacity' => 5000, 'vehicle_status' => 'Running']);
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

        $manifest = \App\Models\Delivery::create(['waybill_numbers' => ['WB-'.$suffix], 'created_by' => $this->admin->id]);

        return ['waybills' => [['dm_id' => $manifest->id, 'waybill_number' => 'WB-'.$suffix, 'drops_number' => 1, 'quantity' => 1, 'kilogram' => 1, 'cbm' => 0.5]], 'version' => 0, 'vehicle_id' => $vehicle->id, 'tariff_id' => $tariff->id, 'trip_no' => '1', 'trip_type' => $type->trip_type, 'payment_type' => $payment->payment_type, 'drops_number' => 2, 'trip_route_city' => 'Manila', 'trip_route_origin' => 'Manila', 'trip_route_destination' => 'Calamba', 'personnels' => [['employee_id' => $employee->id, 'designation' => 'Driver', 'salary' => 0]], 'segments' => [['origin_id' => $origin->id, 'destination_id' => $destination->id, 'total_distance' => 100]], 'fuel_purchases' => [['fuel_supplier' => $supplier->id, 'liters_required' => 10, 'price_per_liter' => 60]], 'costs' => [['cost_code' => $cost->id, 'costgroup' => 'Road', 'is_cash_released' => 1, 'released_amount' => 50, 'nonreleased_amount' => 0]]];
    }
}

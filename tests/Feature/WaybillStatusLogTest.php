<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\WaybillPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaybillStatusLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creating_a_waybill_creates_its_initial_status_log(): void
    {
        $user = $this->administrator();
        $client = $this->client();

        $this->actingAs($user)
            ->post(route('waybills.store'), $this->waybillPayload($client))
            ->assertRedirect(route('waybills.index'));

        $waybillNumber = $client->prefix.'00000001';
        $this->assertDatabaseHas('status_logs', [
            'waybill_number' => $waybillNumber,
            'status' => 'for_pickup',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'is_deleted' => null,
        ]);

        $package = WaybillPackage::where('waybill_number', $waybillNumber)->firstOrFail();
        $package->statusLogs()->create([
            'status' => 'accepted_to_warehouse',
            'reference' => 'REF-123',
            'comment' => 'Package arrived at the warehouse.',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('waybills.show', $package))
            ->assertOk()
            ->assertSee('accepted_to_warehouse')
            ->assertSee('REF-123')
            ->assertSee('Package arrived at the warehouse.')
            ->assertSee($user->name);
    }

    public function test_uploading_waybills_creates_their_initial_status_logs(): void
    {
        Storage::fake('local');
        $user = $this->administrator();
        $client = $this->client();
        $packageId = 'PKG-'.Str::upper(Str::random(10));
        $headers = [
            'order_number', 'package_id', 'package_status', 'package_description', 'package_size',
            'package_quantity', 'package_length', 'package_width', 'package_height', 'package_weight',
            'payment_type', 'total_price', 'declared_value', 'buyer_name', 'buyer_mobile',
            'buyer_full_address', 'buyer_province', 'buyer_city', 'buyer_barangay', 'item_reference',
            'item_description', 'item_sku', 'item_length', 'item_width', 'item_height', 'item_weight',
        ];
        $values = [
            'ORDER-CSV', $packageId, 'accepted_by_courier', 'Uploaded package', 'Medium',
            1, 10, 10, 10, 2, 'COD', 250, 200, 'CSV Buyer', '09170000000',
            '1 CSV Street', 'Metro Manila', 'Manila', 'Port Area', 'ITEM-CSV',
            'Uploaded item', 'SKU-CSV', 10, 10, 10, 2,
        ];
        $file = UploadedFile::fake()->createWithContent('waybills.csv', $this->csv([$headers, $values]));

        $this->actingAs($user)->post(route('waybills.import'), [
            'upload_client_id' => $client->id,
            'file' => $file,
        ])->assertOk();

        $previews = session('waybill_upload_previews');
        $token = array_key_first($previews);
        $this->actingAs($user)->post(route('waybills.import.proceed'), [
            'preview_token' => $token,
        ])->assertOk();

        $this->assertDatabaseHas('status_logs', [
            'waybill_number' => $client->prefix.'00000001',
            'status' => 'accepted_by_courier',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'is_deleted' => null,
        ]);
    }

    private function client(): Client
    {
        $code = 'T'.Str::upper(Str::random(7));

        return Client::create([
            'client_code' => $code,
            'client_name' => 'Test Client '.$code,
            'full_address' => '1 Test Street',
            'province' => 'Metro Manila',
            'city' => 'Manila',
            'barangay' => 'Port Area',
            'focal_person' => 'Test Contact',
            'contact_number' => '09170000000',
            'prefix' => $code.'-',
            'is_active' => true,
        ]);
    }

    private function waybillPayload(Client $client): array
    {
        return [
            'client_id' => $client->id,
            'order_number' => 'ORDER-'.Str::upper(Str::random(10)),
            'package_id' => 'PKG-'.Str::upper(Str::random(10)),
            'package_description' => 'Test package',
            'package_size' => 'Medium',
            'package_quantity' => 1,
            'package_length' => 10,
            'package_width' => 10,
            'package_height' => 10,
            'package_weight' => 2,
            'payment_type' => 'COD',
            'total_price' => 250,
            'declared_value' => 200,
            'buyer_name' => 'Test Buyer',
            'buyer_mobile' => '09170000000',
            'buyer_full_address' => '2 Test Street',
            'buyer_province' => 'Metro Manila',
            'buyer_city' => 'Manila',
            'buyer_barangay' => 'Port Area',
            'seller_name' => $client->client_name,
            'seller_full_address' => $client->full_address,
            'seller_province' => $client->province,
            'seller_city' => $client->city,
            'seller_barangay' => $client->barangay,
            'is_priority' => false,
            'items' => [[
                'item_reference' => 'ITEM-1',
                'item_description' => 'Test item',
                'item_sku' => 'SKU-1',
                'item_length' => 10,
                'item_width' => 10,
                'item_height' => 10,
                'item_weight' => 2,
            ]],
        ];
    }

    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'w+');
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function administrator(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }
}

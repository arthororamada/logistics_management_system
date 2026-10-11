<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApplicationNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backend_flash_messages_are_available_to_the_shared_service_and_safely_encoded(): void
    {
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        $message = 'Saved <script>unsafe()</script> & reviewed';
        $response = $this->withSession(['success' => $message, 'warning' => 'Please review the record.'])
            ->get(route('dashboard'))->assertOk();
        $payload = $this->notificationPayload($response->getContent());
        $this->assertEquals([
            ['type' => 'success', 'message' => $message],
            ['type' => 'warning', 'message' => 'Please review the record.'],
        ], $payload['messages']);
        $response->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_validation_errors_use_the_shared_payload_without_losing_old_input(): void
    {
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        $this->from(route('clients.create'))->post(route('clients.store'), ['client_code' => 'KEEP-MY-INPUT'])
            ->assertRedirect(route('clients.create'))->assertSessionHasErrors('client_name');
        $response = $this->get(route('clients.create'))->assertOk()->assertSee('KEEP-MY-INPUT');
        $payload = $this->notificationPayload($response->getContent());
        $this->assertNotEmpty($payload['errors']);
        $this->assertEquals([], $payload['messages']);
    }

    public function test_login_failures_are_available_to_the_same_global_notifications(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['username' => 'not-an-account', 'password' => 'invalid'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $response = $this->get(route('login'))->assertOk()->assertSee('not-an-account');
        $this->assertContains('The username or password is incorrect.', $this->notificationPayload($response->getContent())['errors']);
    }

    public function test_native_dialogs_cannot_be_reintroduced_in_application_sources(): void
    {
        $files = array_merge(File::allFiles(resource_path('views')), File::allFiles(resource_path('js')));
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w.])(?:window\.)?(?:alert|confirm|prompt)\s*\(/',
                $file->getContents(), 'Use the shared notification service in '.$file->getRelativePathname());
        }
    }

    private function notificationPayload(string $html): array
    {
        $this->assertEquals(1, preg_match('/<script type="application\/json" data-app-notifications>(.*?)<\/script>/s', $html, $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}

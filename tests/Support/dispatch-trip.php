<?php

use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
file_put_contents($argv[2], 'ready');
try {
    DB::transaction(function () use ($argv) {
        $trip = Trip::whereKey($argv[1])->lockForUpdate()->firstOrFail();
        app(TripService::class)->dispatch($trip, $trip->created_by, 'Concurrency test');
    }, 3);
    echo 'dispatched';
} catch (ValidationException $exception) {
    echo 'rejected:'.json_encode($exception->errors());
}

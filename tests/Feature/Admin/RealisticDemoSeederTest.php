<?php

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);
});

/**
 * The PDF routes bind on unique_hash (`/invoices/pdf/{invoice:unique_hash}`), and
 * the seeder builds its documents with Model::create() rather than through the
 * service layer that normally assigns it. When that was missed, every seeded
 * document produced a `/invoices/pdf/` URL with an empty segment — a 404, surfaced
 * in the UI only as "Unable to load document preview", with nothing in the log.
 *
 * Anything the seeder creates that the app then serves by hash has to carry one.
 */
test('every seeded document has the unique hash its pdf route binds on', function (string $model) {
    expect($model::count())->toBeGreaterThan(0);

    expect($model::whereNull('unique_hash')->orWhere('unique_hash', '')->count())
        ->toBe(0);
})->with([
    Invoice::class,
    Estimate::class,
    Payment::class,
]);

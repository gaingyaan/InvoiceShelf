<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Queue::fake();

    $this->importDirectory = 'storage/framework/testing/historical-import-'.Str::uuid();
    File::makeDirectory(base_path($this->importDirectory), 0755, true);
});

afterEach(function () {
    File::deleteDirectory(base_path($this->importDirectory));
});

test('it imports a paid historical invoice from markdown idempotently', function () {
    File::put(base_path($this->importDirectory.'/legacy-invoice.md'), <<<'MARKDOWN'
---
invoice_number: HIST-0001
invoice_date: 2024-01-15
due_date: 2024-01-30
po_number: PO-LEGACY-1
status: PAID
currency: USD
client_name: Legacy Customer
billing_address_1: 10 Archive Road
billing_city: Old Town
billing_country: United States
sub_total: 125.50
tax: 0
total: 125.50
items:
  - name: Historical Consulting
    description: Imported professional services
    qty: 1
    rate: 125.50
    amount: 125.50
---
MARKDOWN);

    $company = Company::query()->firstOrFail();

    $this->artisan('invoices:import-historical', [
        '--path' => $this->importDirectory,
        '--company' => (string) $company->id,
    ])->assertSuccessful();

    $invoice = Invoice::query()
        ->where('company_id', $company->id)
        ->where('invoice_number', 'HIST-0001')
        ->firstOrFail();

    expect($invoice)
        ->total->toBe(12550)
        ->sub_total->toBe(12550)
        ->status->toBe(Invoice::STATUS_PAID)
        ->paid_status->toBe(Invoice::STATUS_PAID)
        ->due_amount->toBe(0)
        ->and($invoice->customer->name)->toBe('Legacy Customer')
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->name)->toBe('Historical Consulting');

    $originalToken = $invoice->unique_hash;

    $this->artisan('invoices:import-historical', [
        '--path' => $this->importDirectory,
        '--company' => (string) $company->id,
    ])->assertSuccessful();

    expect(Invoice::query()->where('invoice_number', 'HIST-0001')->count())->toBe(1)
        ->and($invoice->fresh()->unique_hash)->toBe($originalToken)
        ->and(Payment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('it fails when the import directory does not exist', function () {
    $this->artisan('invoices:import-historical', [
        '--path' => $this->importDirectory.'/missing',
    ])->assertFailed();
});

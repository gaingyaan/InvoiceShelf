<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Ai\Tools\ListRecentPaymentsTool;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('list recent payments returns all allocations and unapplied credit', function () {
    $company = Company::first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'amount' => 1000,
        'base_amount' => 1000,
        'payment_date' => now()->toDateString(),
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 600,
        'base_amount' => 600,
    ]);

    $result = (new ListRecentPaymentsTool)->execute(['days' => 1], $company->id, 1);
    $row = collect($result['payments'])->firstWhere('id', $payment->id);

    expect($row)
        ->toMatchArray([
            'allocated_amount' => 600,
            'unallocated_amount' => 400,
        ])
        ->not->toHaveKey('invoice_id')
        ->and($row['allocations'])->toContain([
            'invoice_id' => $invoice->id,
            'amount' => 600,
        ]);
});

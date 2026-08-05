<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Ai\Application\Tools\SearchInvoicesTool;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('search_invoices scopes strictly to the passed company id', function () {
    $companyA = Company::first();
    $companyB = Company::factory()->create();

    $customerA = Customer::factory()->create(['company_id' => $companyA->id]);
    $customerB = Customer::factory()->create(['company_id' => $companyB->id]);

    $invoiceA = Invoice::factory()->create([
        'company_id' => $companyA->id,
        'customer_id' => $customerA->id,
        'invoice_number' => 'AAA-001',
    ]);
    $invoiceB = Invoice::factory()->create([
        'company_id' => $companyB->id,
        'customer_id' => $customerB->id,
        'invoice_number' => 'BBB-001',
    ]);

    $tool = new SearchInvoicesTool;

    // Call with companyA — should ONLY see invoiceA
    $resultA = $tool->execute([], $companyA->id, 1);
    $numbersA = collect($resultA['invoices'])->pluck('invoice_number');
    expect($numbersA)->toContain('AAA-001')->not->toContain('BBB-001');

    // Call with companyB — should ONLY see invoiceB
    $resultB = $tool->execute([], $companyB->id, 1);
    $numbersB = collect($resultB['invoices'])->pluck('invoice_number');
    expect($numbersB)->toContain('BBB-001')->not->toContain('AAA-001');
});

test('search_invoices ignores any company_id the caller tries to pass in arguments', function () {
    $companyA = Company::first();
    $companyB = Company::factory()->create();

    $customerB = Customer::factory()->create(['company_id' => $companyB->id]);
    Invoice::factory()->create([
        'company_id' => $companyB->id,
        'customer_id' => $customerB->id,
        'invoice_number' => 'BBB-LEAK',
    ]);

    $tool = new SearchInvoicesTool;

    // Simulate an LLM trying to pass company_id as an argument — even if present,
    // the tool must ignore it because the schema doesn't include that field and
    // the execute() method uses the injected $companyId.
    $result = $tool->execute(
        ['company_id' => $companyB->id, 'query' => 'BBB'],
        $companyA->id,
        1,
    );

    expect(collect($result['invoices'])->pluck('invoice_number'))
        ->not->toContain('BBB-LEAK');
});

test('search_invoices respects the limit parameter with a hard cap', function () {
    $company = Company::first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    // Create 60 invoices — more than the max limit of 50
    for ($i = 0; $i < 60; $i++) {
        Invoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SCAN-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        ]);
    }

    $tool = new SearchInvoicesTool;

    // Passing limit=99 should be capped to 50
    $result = $tool->execute(['limit' => 99], $company->id, 1);
    expect($result['invoices'])->toHaveCount(50);

    // Default limit is 10
    $result = $tool->execute([], $company->id, 1);
    expect($result['invoices'])->toHaveCount(10);
});

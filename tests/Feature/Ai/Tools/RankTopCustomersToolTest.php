<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Ai\Application\Tools\RankTopCustomersTool;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('rank_top_customers ranks by invoiced_total correctly', function () {
    $company = Company::first();

    $bigSpender = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Big Spender']);
    $midSpender = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Mid Spender']);
    $smallSpender = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Small Spender']);

    // Big: 2 invoices totalling 100000. Mid: 1 invoice totalling 50000. Small: 1 invoice totalling 10000.
    Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $bigSpender->id, 'total' => 60000]);
    Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $bigSpender->id, 'total' => 40000]);
    Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $midSpender->id, 'total' => 50000]);
    Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $smallSpender->id, 'total' => 10000]);

    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'invoiced_total', 'limit' => 3],
        $company->id,
        1,
    );

    expect($result['metric'])->toBe('invoiced_total');
    expect($result['customers'])->toHaveCount(3);
    expect($result['customers'][0]['name'])->toBe('Big Spender');
    expect($result['customers'][0]['metric_value'])->toBe(100000.0);
    expect($result['customers'][0]['invoice_count'])->toBe(2);
    expect($result['customers'][1]['name'])->toBe('Mid Spender');
    expect($result['customers'][2]['name'])->toBe('Small Spender');
});

test('rank_top_customers ranks by invoice_count correctly', function () {
    $company = Company::first();

    $busy = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Busy Bee']);
    $quiet = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Quiet One']);

    // Busy has 5 tiny invoices, Quiet has 1 big one.
    for ($i = 0; $i < 5; $i++) {
        Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $busy->id, 'total' => 100]);
    }
    Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $quiet->id, 'total' => 99999]);

    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'invoice_count'],
        $company->id,
        1,
    );

    expect($result['customers'][0]['name'])->toBe('Busy Bee');
    expect($result['customers'][0]['metric_value'])->toBe(5);
    expect($result['customers'][1]['name'])->toBe('Quiet One');
    expect($result['customers'][1]['metric_value'])->toBe(1);
});

test('rank_top_customers ranks by outstanding_balance and ignores period', function () {
    $company = Company::first();

    $debtor = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Owes A Lot']);
    $upToDate = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Up To Date']);

    // Debtor has an unpaid invoice with 75000 due_amount.
    Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $debtor->id,
        'total' => 100000,
        'due_amount' => 75000,
        'paid_status' => 'PARTIALLY_PAID',
    ]);

    // Up To Date has only a fully-paid invoice — should NOT appear.
    Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $upToDate->id,
        'total' => 50000,
        'due_amount' => 0,
        'paid_status' => 'PAID',
    ]);

    // Pass an obviously wrong period — outstanding_balance should ignore it.
    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'outstanding_balance', 'period' => 'today'],
        $company->id,
        1,
    );

    expect($result['period'])->toBe('current');
    expect(collect($result['customers'])->pluck('name'))
        ->toContain('Owes A Lot')
        ->not->toContain('Up To Date');
});

test('rank_top_customers respects the limit parameter and default', function () {
    $company = Company::first();

    // 7 customers, each with one invoice
    for ($i = 0; $i < 7; $i++) {
        $c = Customer::factory()->create(['company_id' => $company->id]);
        Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $c->id, 'total' => 1000]);
    }

    $tool = new RankTopCustomersTool;

    // Default limit is 5
    $result = $tool->execute(['metric' => 'invoiced_total'], $company->id, 1);
    expect($result['customers'])->toHaveCount(5);

    // Explicit limit 2
    $result = $tool->execute(['metric' => 'invoiced_total', 'limit' => 2], $company->id, 1);
    expect($result['customers'])->toHaveCount(2);

    // Over-the-cap limit is clamped to 20
    $result = $tool->execute(['metric' => 'invoiced_total', 'limit' => 999], $company->id, 1);
    expect(count($result['customers']))->toBeLessThanOrEqual(20);
});

test('rank_top_customers does not leak across companies', function () {
    $companyA = Company::first();
    $companyB = Company::factory()->create();

    $customerA = Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'Company A Cust']);
    $customerB = Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'Company B Cust']);

    Invoice::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id, 'total' => 5000]);
    Invoice::factory()->create(['company_id' => $companyB->id, 'customer_id' => $customerB->id, 'total' => 999999]);

    // Call with companyA — should only see companyA's customer
    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'invoiced_total'],
        $companyA->id,
        1,
    );

    expect(collect($result['customers'])->pluck('name'))
        ->toContain('Company A Cust')
        ->not->toContain('Company B Cust');
});

test('rank_top_customers rejects an invalid metric', function () {
    $company = Company::first();

    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'not_a_metric'],
        $company->id,
        1,
    );

    expect($result)->toHaveKey('error', 'invalid_metric');
});

test('rank_top_customers ranks by paid_total using payment records', function () {
    $company = Company::first();

    $topPayer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Top Payer']);
    $lowPayer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Low Payer']);

    // Top Payer has 2 payments totalling 80000. Low Payer has 1 payment for 20000.
    Payment::factory()->create(['company_id' => $company->id, 'customer_id' => $topPayer->id, 'amount' => 50000]);
    Payment::factory()->create(['company_id' => $company->id, 'customer_id' => $topPayer->id, 'amount' => 30000]);
    Payment::factory()->create(['company_id' => $company->id, 'customer_id' => $lowPayer->id, 'amount' => 20000]);

    $result = (new RankTopCustomersTool)->execute(
        ['metric' => 'paid_total'],
        $company->id,
        1,
    );

    expect($result['customers'][0]['name'])->toBe('Top Payer');
    expect($result['customers'][0]['metric_value'])->toBe(80000.0);
    expect($result['customers'][1]['name'])->toBe('Low Payer');
    expect($result['customers'][1]['metric_value'])->toBe(20000.0);
});

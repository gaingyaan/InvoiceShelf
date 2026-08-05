<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Platform\Ai\Application\Tools\RankTopItemsTool;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

/**
 * Helper: create an Item with a known name/price for this company. The Item
 * factory has known bugs (cascades RecurringInvoice, hardcoded creator_id),
 * so we use direct ::create() for determinism.
 */
function makeItem(int $companyId, string $name, int $price): Item
{
    $unit = Unit::where('company_id', $companyId)->firstOrFail();

    return Item::create([
        'name' => $name,
        'description' => $name.' description',
        'price' => $price,
        'company_id' => $companyId,
        'unit_id' => $unit->id,
        'currency_id' => 1,
    ]);
}

test('rank_top_items ranks by revenue correctly', function () {
    $company = Company::first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $premium = makeItem($company->id, 'Premium Widget', 10000);
    $standard = makeItem($company->id, 'Standard Widget', 5000);
    $budget = makeItem($company->id, 'Budget Widget', 1000);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);

    // Premium: 2 × 10000 = 20000
    // Standard: 3 × 5000 = 15000
    // Budget: 5 × 1000 = 5000
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $premium->id, 'quantity' => 2, 'price' => 10000, 'total' => 20000,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $standard->id, 'quantity' => 3, 'price' => 5000, 'total' => 15000,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $budget->id, 'quantity' => 5, 'price' => 1000, 'total' => 5000,
    ]);

    $result = (new RankTopItemsTool)->execute(
        ['metric' => 'revenue'],
        $company->id,
        1,
    );

    expect($result['metric'])->toBe('revenue');
    expect($result['items'][0]['name'])->toBe('Premium Widget');
    expect($result['items'][0]['revenue'])->toBe(20000.0);
    expect($result['items'][1]['name'])->toBe('Standard Widget');
    expect($result['items'][2]['name'])->toBe('Budget Widget');
});

test('rank_top_items ranks by quantity_sold correctly', function () {
    $company = Company::first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $bulky = makeItem($company->id, 'Bulk Good', 100);
    $pricey = makeItem($company->id, 'Pricey Good', 50000);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);

    // Bulky: sold 50 units at 100 each = 5000 revenue
    // Pricey: sold 1 unit at 50000 = 50000 revenue
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $bulky->id, 'quantity' => 50, 'price' => 100, 'total' => 5000,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $pricey->id, 'quantity' => 1, 'price' => 50000, 'total' => 50000,
    ]);

    // By quantity: Bulky wins (50 > 1)
    $result = (new RankTopItemsTool)->execute(
        ['metric' => 'quantity_sold'],
        $company->id,
        1,
    );

    expect($result['items'][0]['name'])->toBe('Bulk Good');
    expect($result['items'][0]['quantity_sold'])->toBe(50.0);

    // By revenue: Pricey wins (50000 > 5000)
    $result = (new RankTopItemsTool)->execute(
        ['metric' => 'revenue'],
        $company->id,
        1,
    );

    expect($result['items'][0]['name'])->toBe('Pricey Good');
});

test('rank_top_items excludes ad-hoc line items with null item_id', function () {
    $company = Company::first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $cataloged = makeItem($company->id, 'Real Item', 1000);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);

    // Cataloged item on the invoice
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id, 'company_id' => $company->id,
        'item_id' => $cataloged->id, 'quantity' => 1, 'price' => 1000, 'total' => 1000,
    ]);

    // Ad-hoc line without a catalog item — simulate via direct ::create
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'company_id' => $company->id,
        'item_id' => null,
        'name' => 'Ad hoc typed line',
        'price' => 9999,
        'quantity' => 1,
        'total' => 9999,
        'discount' => 0,
        'discount_val' => 0,
        'discount_type' => 'fixed',
        'tax' => 0,
        'base_price' => 9999,
        'base_total' => 9999,
        'base_discount_val' => 0,
        'base_tax' => 0,
        'exchange_rate' => 1,
    ]);

    $result = (new RankTopItemsTool)->execute(
        ['metric' => 'revenue'],
        $company->id,
        1,
    );

    // Only the cataloged item should appear — ad-hoc line is excluded by whereNotNull.
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]['name'])->toBe('Real Item');
});

test('rank_top_items does not leak across companies', function () {
    $companyA = Company::first();
    $companyB = Company::factory()->create();

    $itemA = makeItem($companyA->id, 'A Only Item', 1000);

    $customerA = Customer::factory()->create(['company_id' => $companyA->id]);
    $invoiceA = Invoice::factory()->create([
        'company_id' => $companyA->id,
        'customer_id' => $customerA->id,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoiceA->id, 'company_id' => $companyA->id,
        'item_id' => $itemA->id, 'quantity' => 1, 'price' => 1000, 'total' => 1000,
    ]);

    // Call with companyB — should see no items at all
    $result = (new RankTopItemsTool)->execute(
        ['metric' => 'revenue'],
        $companyB->id,
        1,
    );

    expect($result['items'])->toBeEmpty();
});

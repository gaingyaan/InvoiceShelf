<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Platform\Ai\Application\Tools\RankExpenseCategoriesTool;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('rank_expense_categories orders categories by total spend', function () {
    $company = Company::first();

    $software = ExpenseCategory::factory()->create(['company_id' => $company->id, 'name' => 'Software']);
    $travel = ExpenseCategory::factory()->create(['company_id' => $company->id, 'name' => 'Travel']);
    $office = ExpenseCategory::factory()->create(['company_id' => $company->id, 'name' => 'Office Supplies']);

    // Software: 2 expenses totalling 30000
    Expense::factory()->create([
        'company_id' => $company->id, 'expense_category_id' => $software->id, 'amount' => 20000,
    ]);
    Expense::factory()->create([
        'company_id' => $company->id, 'expense_category_id' => $software->id, 'amount' => 10000,
    ]);

    // Travel: 1 expense, 15000
    Expense::factory()->create([
        'company_id' => $company->id, 'expense_category_id' => $travel->id, 'amount' => 15000,
    ]);

    // Office: 1 expense, 2000
    Expense::factory()->create([
        'company_id' => $company->id, 'expense_category_id' => $office->id, 'amount' => 2000,
    ]);

    $result = (new RankExpenseCategoriesTool)->execute([], $company->id, 1);

    // Grab only the three we created
    $byName = collect($result['categories'])->keyBy('name');

    expect($byName['Software']['total_amount'])->toBe(30000.0);
    expect($byName['Software']['expense_count'])->toBe(2);
    expect($byName['Travel']['total_amount'])->toBe(15000.0);
    expect($byName['Office Supplies']['total_amount'])->toBe(2000.0);

    // Ordering: Software (30k) > Travel (15k) > Office (2k). First three positions should be ours.
    $names = collect($result['categories'])->pluck('name')->values()->all();
    $idxSoftware = array_search('Software', $names, true);
    $idxTravel = array_search('Travel', $names, true);
    $idxOffice = array_search('Office Supplies', $names, true);
    expect($idxSoftware)->toBeLessThan($idxTravel);
    expect($idxTravel)->toBeLessThan($idxOffice);
});

test('rank_expense_categories does not leak across companies', function () {
    $companyA = Company::first();
    $companyB = Company::factory()->create();

    $catA = ExpenseCategory::factory()->create(['company_id' => $companyA->id, 'name' => 'Company A Cat']);
    $catB = ExpenseCategory::factory()->create(['company_id' => $companyB->id, 'name' => 'Company B Cat']);

    Expense::factory()->create([
        'company_id' => $companyA->id, 'expense_category_id' => $catA->id, 'amount' => 1000,
    ]);
    Expense::factory()->create([
        'company_id' => $companyB->id, 'expense_category_id' => $catB->id, 'amount' => 999999,
    ]);

    // Call with companyA — should only see A's category
    $result = (new RankExpenseCategoriesTool)->execute([], $companyA->id, 1);

    expect(collect($result['categories'])->pluck('name'))
        ->toContain('Company A Cat')
        ->not->toContain('Company B Cat');
});

test('rank_expense_categories rejects an invalid period', function () {
    $company = Company::first();

    $result = (new RankExpenseCategoriesTool)->execute(
        ['period' => 'next_century'],
        $company->id,
        1,
    );

    expect($result)->toHaveKey('error', 'invalid_period');
});

test('rank_expense_categories respects the limit parameter', function () {
    $company = Company::first();

    // Create 6 categories each with one expense
    for ($i = 0; $i < 6; $i++) {
        $cat = ExpenseCategory::factory()->create([
            'company_id' => $company->id,
            'name' => "TestCat {$i}",
        ]);
        Expense::factory()->create([
            'company_id' => $company->id,
            'expense_category_id' => $cat->id,
            'amount' => 1000 * ($i + 1),
        ]);
    }

    $result = (new RankExpenseCategoriesTool)->execute(['limit' => 3], $company->id, 1);

    expect($result['categories'])->toHaveCount(3);
});

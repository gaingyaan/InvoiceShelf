<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Taxation\Http\Controllers\TaxTypesController;
use App\Domains\Taxation\Http\Requests\TaxTypeRequest;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get tax types', function () {
    $response = getJson('api/v1/tax-types');

    $response->assertOk();
});

test('create tax type', function () {
    $taxType = TaxType::factory()->raw();

    postJson('api/v1/tax-types', $taxType);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        TaxTypesController::class,
        'store',
        TaxTypeRequest::class
    );
});

test('get tax type', function () {
    $taxType = TaxType::factory()->create();

    $response = getJson('api/v1/tax-types/'.$taxType->id);

    $response->assertOk();
});

test('update tax type', function () {
    $taxType = TaxType::factory()->create();

    $taxType1 = TaxType::factory()->raw();

    $response = putJson('api/v1/tax-types/'.$taxType->id, $taxType1);

    $response->assertOk();
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        TaxTypesController::class,
        'update',
        TaxTypeRequest::class
    );
});

test('delete tax type', function () {
    $taxType = TaxType::factory()->create();

    $response = deleteJson('api/v1/tax-types/'.$taxType->id);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertModelMissing($taxType);
});

test('create negative tax type', function () {
    $taxType = TaxType::factory()->raw([
        'percent' => -9.99,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('create fixed amount tax type', function () {
    $taxType = TaxType::factory()->raw([
        'calculation_type' => 'fixed',
        'percent' => null,
        'fixed_amount' => 5000,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('defaults tax type transaction type to sales for legacy create requests', function () {
    $taxType = TaxType::factory()->raw();
    unset($taxType['transaction_type']);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_SALES);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);
});

test('creates purchase tax types and returns their transaction type', function () {
    $taxType = TaxType::factory()->raw([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('preserves transaction type when legacy updates omit it', function () {
    $taxType = TaxType::factory()->create([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $payload = TaxType::factory()->raw();
    unset($payload['transaction_type']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
});

test('filters tax types by transaction type', function () {
    $companyId = User::find(1)->companies()->first()->id;
    TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);
    $purchaseTaxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    getJson('api/v1/tax-types?limit=all&transaction_type=purchases')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $purchaseTaxType->id)
        ->assertJsonPath('data.0.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);
});

test('rejects unknown transaction types', function () {
    $taxType = TaxType::factory()->raw(['transaction_type' => 'other']);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transaction_type');
});

test('creates a compound tax type', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => true,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201)
        ->assertJsonPath('data.compound_tax', true);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'compound_tax' => 1,
    ]);
});

test('creates a non-compound tax type when compound_tax is explicitly false', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => false,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201)
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'compound_tax' => 0,
    ]);
});

test('updates a tax type to explicitly disable compound tax', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
    ]);

    $payload = TaxType::factory()->raw([
        'compound_tax' => false,
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'compound_tax' => 0,
    ]);
});

test('preserves compound tax when updates omit the key', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
    ]);

    $payload = TaxType::factory()->raw();
    // TaxType::factory()->raw() defaults compound_tax to 0 — unset it so the
    // request omits the key entirely, instead of silently sending false.
    unset($payload['compound_tax']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.compound_tax', true);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'compound_tax' => 1,
    ]);
});

test('rejects non-boolean compound_tax values', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => 'not-a-bool',
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

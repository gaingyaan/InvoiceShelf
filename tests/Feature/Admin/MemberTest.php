<?php

use App\Domains\Accounts\Http\Controllers\Company\MembersController;
use App\Domains\Accounts\Http\Requests\MemberRequest;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('list members', function () {
    getJson('/api/v1/members')->assertOk();
});

test('store member using a form request', function () {
    $this->assertActionUsesFormRequest(
        MembersController::class,
        'store',
        MemberRequest::class
    );
});

test('get member belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    getJson("/api/v1/members/{$user->id}")->assertOk();
});

test('cannot view a member belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    getJson("/api/v1/members/{$user->id}")->assertForbidden();
});

test('cannot update a member belonging to another company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    putJson("/api/v1/members/{$user->id}", [
        'name' => 'Hacked',
        'email' => 'pwned@attacker.test',
        'companies' => [['id' => $companyId, 'role' => 'super admin']],
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'pwned@attacker.test']);
});

test('update member using a form request', function () {
    $this->assertActionUsesFormRequest(
        MembersController::class,
        'update',
        MemberRequest::class
    );
});

test('deletes a member belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    postJson('/api/v1/members/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('cannot bulk delete a member belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    postJson('/api/v1/members/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

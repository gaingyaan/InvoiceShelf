<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('expense belongs to category', function () {
    $expense = Expense::factory()->forCategory()->create();

    $this->assertTrue($expense->category()->exists());
});

test('expense belongs to customer', function () {
    $expense = Expense::factory()->forCustomer()->create();

    $this->assertTrue($expense->customer()->exists());
});

test('expense belongs to company', function () {
    $expense = Expense::factory()->forCompany()->create();

    $this->assertTrue($expense->company()->exists());
});

test('expense has taxes and deletes them with the expense', function () {
    $expense = Expense::factory()->create();
    $taxType = TaxType::factory()->create(['transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES]);
    $tax = Tax::factory()->create(['expense_id' => $expense->id, 'tax_type_id' => $taxType->id]);

    expect($expense->taxes)->toHaveCount(1);

    $expense->delete();

    $this->assertDatabaseMissing('taxes', ['id' => $tax->id]);
});

test('customer deletion removes receipt taxes through expense model events', function () {
    $expense = Expense::factory()->forCustomer()->create();
    $taxType = TaxType::factory()->create(['transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES]);
    $tax = Tax::factory()->create(['expense_id' => $expense->id, 'tax_type_id' => $taxType->id]);

    app(CustomerService::class)->delete(collect([$expense->customer_id]));

    $this->assertDatabaseMissing('taxes', ['id' => $tax->id]);
});

test('company deletion removes receipt taxes through expense model events', function () {
    $user = User::find(1);
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $user->companies()->attach($company);
    $expense = Expense::factory()->create(['company_id' => $company->id]);
    $taxType = TaxType::factory()->create([
        'company_id' => $company->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $tax = Tax::factory()->create([
        'expense_id' => $expense->id,
        'company_id' => $company->id,
        'tax_type_id' => $taxType->id,
    ]);

    app(CompanyService::class)->delete($company, $user);

    $this->assertDatabaseMissing('taxes', ['id' => $tax->id]);
});

test('customer deletion removes payment allocations before bulk payment deletion', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
    ]);
    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
    ]);

    app(CustomerService::class)->delete(collect([$customer->id]));

    $this->assertDatabaseMissing('payment_allocations', ['id' => $allocation->id]);
});

test('company deletion removes payment allocations before bulk payment deletion', function () {
    $user = User::find(1);
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $user->companies()->attach($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);
    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
    ]);

    app(CompanyService::class)->delete($company, $user);

    $this->assertDatabaseMissing('payment_allocations', ['id' => $allocation->id]);
});

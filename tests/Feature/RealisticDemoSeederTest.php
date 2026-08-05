<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    Queue::fake();
});

test('realistic demo payments are allocated and leave invoice balances consistent', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $company = User::where('email', 'demo@invoiceshelf.com')->firstOrFail()->companies()->firstOrFail();
    $payments = Payment::query()->where('company_id', $company->id)->with('allocations')->get();
    $allocatedInvoices = Invoice::query()
        ->where('company_id', $company->id)
        ->whereHas('allocations')
        ->with('allocations')
        ->get();

    expect($payments)->not->toBeEmpty()
        ->and($payments->every(fn (Payment $payment) => $payment->allocations->isNotEmpty()))->toBeTrue()
        ->and(PaymentAllocation::query()->whereIn('payment_id', $payments->modelKeys())->count())->toBe($payments->count());

    foreach ($allocatedInvoices as $invoice) {
        expect((int) $invoice->due_amount)
            ->toBe(max(0, (int) $invoice->total - (int) $invoice->allocations->sum('amount')));
    }
});

test('realistic demo provides current-month account activity for every customer', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $company = User::where('email', 'demo@invoiceshelf.com')->firstOrFail()->companies()->firstOrFail();
    $from = Carbon::now()->startOfMonth();
    $to = Carbon::now();
    $statementService = app(CustomerStatementService::class);

    Customer::query()
        ->where('company_id', $company->id)
        ->each(function (Customer $customer) use ($statementService, $from, $to): void {
            $statement = $statementService->statement(
                $customer,
                CustomerStatementService::TYPE_ACTIVITY,
                $from,
                $to,
            );

            expect($statement['entries']->total())->toBeGreaterThan(0);
        });
});

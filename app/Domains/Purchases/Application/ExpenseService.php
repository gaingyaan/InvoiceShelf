<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Purchases\Contracts\ExpenseExchangeRateRecorder;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly ExpenseTaxManager $expenseTaxManager,
        private readonly ExpenseExchangeRateRecorder $expenseExchangeRateRecorder,
        private readonly ExpenseReceiptManager $expenseReceiptManager,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{tax_type_id: int, amount: int}>|null  $taxes
     */
    public function create(
        array $attributes,
        ?array $taxes = null,
        ?PendingExpenseReceipt $receipt = null,
        ?iterable $customFields = null,
    ): Expense {
        $expense = DB::transaction(function () use ($attributes, $taxes): Expense {
            $expense = Expense::create($attributes);

            if ($taxes !== null) {
                $this->expenseTaxManager->replace($expense, $taxes);
            }

            return $expense;
        });

        $companyCurrency = CompanySetting::getSetting('currency', $expense->company_id);

        if ((string) $expense['currency_id'] !== $companyCurrency) {
            $this->expenseExchangeRateRecorder->record($expense);
        }

        if ($receipt) {
            $this->expenseReceiptManager->attach($expense, $receipt);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($expense, $customFields);
        }

        return $expense->load('taxes.taxType');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{tax_type_id: int, amount: int}>|null  $taxes
     */
    public function update(
        Expense $expense,
        array $attributes,
        ?array $taxes = null,
        ?PendingExpenseReceipt $receipt = null,
        bool $removeReceipt = false,
        ?iterable $customFields = null,
    ): Expense {
        DB::transaction(function () use ($expense, $attributes, $taxes): void {
            $expense->update($attributes);

            if ($taxes !== null) {
                $this->expenseTaxManager->replace($expense, $taxes);
            }
        });

        $companyCurrency = CompanySetting::getSetting('currency', $expense->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->expenseExchangeRateRecorder->record($expense);
        }

        if ($removeReceipt) {
            $this->expenseReceiptManager->clear($expense);
        }

        if ($receipt) {
            $this->expenseReceiptManager->replace($expense, $receipt);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($expense, $customFields);
        }

        return $expense->fresh('taxes.taxType');
    }
}

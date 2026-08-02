<?php

namespace App\Services\Document;

use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;
use App\Models\TaxType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function create(Request $request): Expense
    {
        $expense = DB::transaction(function () use ($request): Expense {
            $expense = Expense::create($request->getExpensePayload());

            if ($request->has('taxes')) {
                $this->syncTaxes($expense, $request->input('taxes'));
            }

            return $expense;
        });

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $expense['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if ($request->hasFile('attachment_receipt')) {
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->addCustomFields(json_decode($request->customFields));
        }

        return $expense->load('taxes.taxType');
    }

    public function update(Expense $expense, Request $request): Expense
    {
        $data = $request->getExpensePayload();

        DB::transaction(function () use ($expense, $data, $request): void {
            $expense->update($data);

            if ($request->has('taxes')) {
                $this->syncTaxes($expense, $request->input('taxes'));
            }
        });

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if (isset($request->is_attachment_receipt_removed) && (bool) $request->is_attachment_receipt_removed) {
            $expense->clearMediaCollection('receipts');
        }
        if ($request->hasFile('attachment_receipt')) {
            $expense->clearMediaCollection('receipts');
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->updateCustomFields(json_decode($request->customFields));
        }

        return $expense->fresh('taxes.taxType');
    }

    /**
     * Replace an expense's receipt tax snapshots with the submitted tax amounts.
     */
    private function syncTaxes(Expense $expense, array $taxes): void
    {
        $expense->taxes()->delete();

        if ($taxes === []) {
            return;
        }

        $taxTypes = TaxType::query()
            ->where('company_id', $expense->company_id)
            ->where('type', TaxType::TYPE_GENERAL)
            ->whereTransactionType(TaxType::TRANSACTION_TYPE_PURCHASES)
            ->whereIn('id', collect($taxes)->pluck('tax_type_id'))
            ->get()
            ->keyBy('id');

        foreach ($taxes as $tax) {
            $taxType = $taxTypes->get($tax['tax_type_id']);

            $expense->taxes()->create([
                'tax_type_id' => $taxType->id,
                'company_id' => $expense->company_id,
                'currency_id' => $expense->currency_id,
                'exchange_rate' => $expense->exchange_rate,
                'amount' => (int) $tax['amount'],
                'base_amount' => (int) round($tax['amount'] * $expense->exchange_rate),
                'name' => $taxType->name,
                'percent' => $taxType->percent,
                'fixed_amount' => $taxType->fixed_amount,
                'calculation_type' => $taxType->calculation_type,
                'compound_tax' => $taxType->compound_tax,
            ]);
        }
    }
}

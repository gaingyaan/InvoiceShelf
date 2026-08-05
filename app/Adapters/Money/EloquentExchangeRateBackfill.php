<?php

namespace App\Adapters\Money;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;

/**
 * Coordinates the one-time exchange-rate backfill across durable document
 * tables while the Money context owns the workflow contract.
 */
class EloquentExchangeRateBackfill implements ExchangeRateBackfill
{
    public function currencyIdsMissingRates(): array
    {
        return array_merge(
            Invoice::whereNull('exchange_rate')->pluck('currency_id')->all(),
            Tax::whereNull('exchange_rate')->pluck('currency_id')->all(),
            Estimate::whereNull('exchange_rate')->pluck('currency_id')->all(),
            Payment::whereNull('exchange_rate')->pluck('currency_id')->all(),
        );
    }

    public function apply(int $companyId, array $currencies): bool
    {
        if (CompanySetting::getSetting('bulk_exchange_rate_configured', $companyId) !== 'NO') {
            return false;
        }

        foreach ($currencies as $currency) {
            $rate = $currency['exchange_rate'] ?? 1;

            foreach (Invoice::where('currency_id', $currency['id'])->get() as $invoice) {
                $invoice->update([
                    'exchange_rate' => $rate,
                    'base_discount_val' => $invoice->sub_total * $rate,
                    'base_sub_total' => $invoice->sub_total * $rate,
                    'base_total' => $invoice->total * $rate,
                    'base_tax' => $invoice->tax * $rate,
                    'base_due_amount' => $invoice->due_amount * $rate,
                ]);

                $this->updateItemsExchangeRate($invoice);
            }

            foreach (Estimate::where('currency_id', $currency['id'])->get() as $estimate) {
                $estimate->update([
                    'exchange_rate' => $rate,
                    'base_discount_val' => $estimate->sub_total * $rate,
                    'base_sub_total' => $estimate->sub_total * $rate,
                    'base_total' => $estimate->total * $rate,
                    'base_tax' => $estimate->tax * $rate,
                ]);

                $this->updateItemsExchangeRate($estimate);
            }

            foreach (Tax::where('currency_id', $currency['id'])->get() as $tax) {
                $tax->update(['base_amount' => $tax->base_amount * $rate]);
            }

            foreach (Payment::where('currency_id', $currency['id'])->get() as $payment) {
                $payment->update([
                    'exchange_rate' => $rate,
                    'base_amount' => $payment->amount * $rate,
                ]);
            }
        }

        CompanySetting::setSettings([
            'bulk_exchange_rate_configured' => 'YES',
        ], $companyId);

        return true;
    }

    private function updateItemsExchangeRate(mixed $document): void
    {
        foreach ($document->items as $item) {
            $item->update([
                'exchange_rate' => $document->exchange_rate,
                'base_discount_val' => $item->discount_val * $document->exchange_rate,
                'base_price' => $item->price * $document->exchange_rate,
                'base_tax' => $item->tax * $document->exchange_rate,
                'base_total' => $item->total * $document->exchange_rate,
            ]);

            $this->updateTaxesExchangeRate($item);
        }

        $this->updateTaxesExchangeRate($document);
    }

    private function updateTaxesExchangeRate(mixed $taxable): void
    {
        if (! $taxable->taxes()->exists()) {
            return;
        }

        $taxable->taxes->each(function ($tax) use ($taxable): void {
            $tax->update([
                'exchange_rate' => $taxable->exchange_rate,
                'base_amount' => $tax->amount * $taxable->exchange_rate,
            ]);
        });
    }
}

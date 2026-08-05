<?php

namespace App\Domains\Receivables\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->with('invoice')->get();
        $allocatedAmount = (int) $allocations->sum('amount');
        $baseAllocatedAmount = (int) $allocations->sum('base_amount');
        $baseAmount = $this->base_amount === null
            ? (int) round($this->amount * ($this->exchange_rate ?: 1))
            : (int) $this->base_amount;

        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date,
            'notes' => $this->getNotes(),
            'amount' => $this->amount,
            'unique_hash' => $this->unique_hash,
            'company_id' => $this->company_id,
            'payment_method_id' => $this->payment_method_id,
            'creator_id' => $this->creator_id,
            'customer_id' => $this->customer_id,
            'exchange_rate' => $this->exchange_rate,
            'base_amount' => $baseAmount,
            'allocations' => $allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'invoice_id' => $allocation->invoice_id,
                'amount' => $allocation->amount,
                'base_amount' => $allocation->base_amount,
                'invoice' => $allocation->invoice ? new InvoiceResource($allocation->invoice) : null,
            ]),
            'allocated_amount' => $allocatedAmount,
            'unallocated_amount' => (int) ((int) $this->amount - $allocatedAmount),
            'base_allocated_amount' => $baseAllocatedAmount,
            'base_unallocated_amount' => (int) ($baseAmount - $baseAllocatedAmount),
            'currency_id' => $this->currency_id,
            'transaction_id' => $this->transaction_id,
            'sequence_number' => $this->sequence_number,
            'formatted_created_at' => $this->formattedCreatedAt,
            'formatted_payment_date' => $this->formattedPaymentDate,
            'payment_pdf_url' => $this->paymentPdfUrl,
            'customer' => $this->when($this->customer()->exists(), function () {
                return new CustomerResource($this->customer);
            }),
            'payment_method' => $this->when($this->paymentMethod()->exists(), function () {
                return new PaymentMethodResource($this->paymentMethod);
            }),
            'fields' => $this->when($this->fields()->exists(), function () {
                return CustomFieldValueResource::collection($this->fields);
            }),
            'company' => $this->when($this->company()->exists(), function () {
                return new CompanyResource($this->company);
            }),
            'currency' => $this->when($this->currency()->exists(), function () {
                return new CurrencyResource($this->currency);
            }),
            'transaction' => $this->when($this->transaction()->exists(), function () {
                return new TransactionResource($this->transaction);
            }),
        ];
    }
}

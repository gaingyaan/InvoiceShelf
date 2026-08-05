<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'payment_date' => [
                'required',
            ],
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('company_id', $this->header('company')),
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_number' => [
                'required',
                Rule::unique('payments')->where('company_id', $this->header('company')),
            ],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['payment_number'] = [
                'required',
                Rule::unique('payments')
                    ->ignore($this->route('payment')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                    'numeric',
                    'gt:0',
                ];
            }
        }

        return $rules;
    }

    /**
     * Reject the retired field without advertising it in the generated API
     * schema. Payment-to-invoice links now exist only inside allocations.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exists('invoice_id')) {
                $validator->errors()->add(
                    'invoice_id',
                    __('validation.prohibited', ['attribute' => 'invoice id'])
                );
            }
        });
    }

    public function getPaymentPayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $currency = Customer::find($this->customer_id)->currency_id;
        $exchange_rate = (string) $company_currency !== (string) $currency
            ? (float) $this->exchange_rate
            : 1;

        return collect($this->validated())
            ->except('allocations')
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $exchange_rate,
                'base_amount' => (int) round($this->amount * $exchange_rate),
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}

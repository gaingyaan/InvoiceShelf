<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('taxes'))) {
            $taxes = json_decode($this->input('taxes'), true);

            $this->merge([
                'taxes' => json_last_error() === JSON_ERROR_NONE ? $taxes : null,
            ]);
        }
    }

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
        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $rules = [
            'expense_date' => [
                'required',
            ],
            'expense_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'expense_category_id' => [
                'required',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'payment_method_id' => [
                'nullable',
            ],
            'amount' => [
                'required',
                'integer',
                'min:0',
            ],
            'customer_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
            'currency_id' => [
                'required',
            ],
            'attachment_receipt' => [
                'nullable',
                'file',
                'mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20000',
            ],
            'taxes' => [
                'sometimes',
                'array',
            ],
            'taxes.*' => [
                'required',
                'array:tax_type_id,amount',
            ],
            'taxes.*.tax_type_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tax_types', 'id')
                    ->where('company_id', $this->header('company'))
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES),
            ],
            'taxes.*.amount' => [
                'required',
                'integer',
                'min:0',
            ],
        ];

        if ($companyCurrency && $this->currency_id) {
            if ($companyCurrency !== $this->currency_id) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $taxes = $this->input('taxes');

            if (! is_array($taxes) || $validator->errors()->has('taxes') || $validator->errors()->has('taxes.*')) {
                return;
            }

            $totalTaxAmount = collect($taxes)->sum(
                fn (mixed $tax): int => is_array($tax) ? (int) ($tax['amount'] ?? 0) : 0
            );

            if ($totalTaxAmount > (int) $this->input('amount')) {
                $validator->errors()->add('taxes', 'The total tax amount may not exceed the expense amount.');
            }
        });
    }

    public function getExpensePayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;

        return collect($this->validated())
            ->except('taxes')
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $exchange_rate,
                'base_amount' => $this->amount * $exchange_rate,
                'currency_id' => $current_currency,
            ])
            ->toArray();
    }
}

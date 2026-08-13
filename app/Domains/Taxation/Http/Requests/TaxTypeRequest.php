<?php

namespace App\Domains\Taxation\Http\Requests;

use App\Domains\Taxation\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaxTypeRequest extends FormRequest
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
            'name' => [
                'required',
                Rule::unique('tax_types')
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('company_id', $this->header('company')),
            ],
            'calculation_type' => [
                'required',
                Rule::in(['percentage', 'fixed']),
            ],
            'percent' => [
                'nullable',
                'numeric',
            ],
            'fixed_amount' => [
                'nullable',
                'numeric',
            ],
            'description' => [
                'nullable',
            ],
            'compound_tax' => [
                'sometimes',
                'boolean',
            ],
            'collective_tax' => [
                'nullable',
            ],
            'transaction_type' => [
                'sometimes',
                Rule::in([
                    TaxType::TRANSACTION_TYPE_SALES,
                    TaxType::TRANSACTION_TYPE_PURCHASES,
                ]),
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['name'] = [
                'required',
                Rule::unique('tax_types')
                    ->ignore($this->route('tax_type')->id)
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->effectiveCompoundTax()) {
                return;
            }

            if (
                $this->effectiveCalculationType() !== 'percentage'
                || $this->effectiveTransactionType() !== TaxType::TRANSACTION_TYPE_SALES
            ) {
                $validator->errors()->add(
                    'compound_tax',
                    'Compound tax is only available for percentage sales taxes.'
                );
            }
        });
    }

    public function getTaxTypePayload()
    {
        $payload = collect($this->validated());

        if (! $payload->has('transaction_type')) {
            $payload->put(
                'transaction_type',
                ($this->isMethod('PUT') || $this->isMethod('PATCH'))
                    ? $this->route('tax_type')->transaction_type
                    : TaxType::TRANSACTION_TYPE_SALES
            );
        }

        if (! $payload->has('compound_tax') && ! ($this->isMethod('PUT') || $this->isMethod('PATCH'))) {
            $payload->put('compound_tax', false);
        }

        return $payload
            ->merge([
                'company_id' => $this->header('company'),
                'type' => TaxType::TYPE_GENERAL,
            ])
            ->toArray();
    }

    private function effectiveCompoundTax(): bool
    {
        if ($this->has('compound_tax')) {
            return $this->boolean('compound_tax');
        }

        return $this->isMethod('PUT') || $this->isMethod('PATCH')
            ? $this->route('tax_type')->compound_tax
            : false;
    }

    private function effectiveCalculationType(): string
    {
        if ($this->has('calculation_type')) {
            return $this->input('calculation_type');
        }

        return $this->isMethod('PUT') || $this->isMethod('PATCH')
            ? $this->route('tax_type')->calculation_type
            : 'percentage';
    }

    private function effectiveTransactionType(): string
    {
        return $this->input('transaction_type')
            ?? ($this->isMethod('PUT') || $this->isMethod('PATCH')
                ? $this->route('tax_type')->transaction_type
                : TaxType::TRANSACTION_TYPE_SALES);
    }
}

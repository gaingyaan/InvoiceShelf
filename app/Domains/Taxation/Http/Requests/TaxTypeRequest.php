<?php

namespace App\Domains\Taxation\Http\Requests;

use App\Domains\Taxation\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                'nullable',
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

        return $payload
            ->merge([
                'company_id' => $this->header('company'),
                'type' => TaxType::TYPE_GENERAL,
            ])
            ->toArray();
    }
}

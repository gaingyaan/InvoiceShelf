<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditAllocationRequest extends FormRequest
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
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.payment_id' => ['required', 'integer'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}

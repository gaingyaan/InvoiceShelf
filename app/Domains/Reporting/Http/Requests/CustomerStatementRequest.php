<?php

namespace App\Domains\Reporting\Http\Requests;

use App\Domains\Reporting\Queries\CustomerStatementQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStatementRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([CustomerStatementQuery::TYPE_ACTIVITY, CustomerStatementQuery::TYPE_OUTSTANDING])],
            'from_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity', 'after_or_equal:from_date'],
            'as_of' => ['nullable', 'date_format:Y-m-d', 'required_if:type,outstanding'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type', CustomerStatementQuery::TYPE_ACTIVITY);
        $today = Carbon::today();

        $this->merge([
            'type' => $type,
            'from_date' => $this->input('from_date', $today->copy()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', $today->toDateString()),
            'as_of' => $this->input('as_of', $today->toDateString()),
        ]);
    }
}

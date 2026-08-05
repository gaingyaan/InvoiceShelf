<?php

namespace App\Http\Requests;

use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCustomerStatementRequest extends FormRequest
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
            'type' => ['required', Rule::in([CustomerStatementService::TYPE_ACTIVITY, CustomerStatementService::TYPE_OUTSTANDING])],
            'from_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,activity', 'after_or_equal:from_date'],
            'as_of' => ['nullable', 'date_format:Y-m-d', 'required_if:type,outstanding'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'to' => ['required', 'email'],
            'cc' => ['nullable', 'email'],
            'bcc' => ['nullable', 'email'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type', CustomerStatementService::TYPE_ACTIVITY);
        $today = Carbon::today();

        $this->merge([
            'type' => $type,
            'from_date' => $this->input('from_date', $today->copy()->startOfMonth()->toDateString()),
            'to_date' => $this->input('to_date', $today->toDateString()),
            'as_of' => $this->input('as_of', $today->toDateString()),
        ]);
    }
}

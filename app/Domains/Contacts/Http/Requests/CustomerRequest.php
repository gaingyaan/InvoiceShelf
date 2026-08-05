<?php

namespace App\Domains\Contacts\Http\Requests;

use App\Domains\Contacts\Models\Address;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
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
            ],
            'email' => [
                new IdnEmail,
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company')),
            ],
            'password' => [
                'nullable',
            ],
            'phone' => [
                'nullable',
            ],
            'company_name' => [
                'nullable',
            ],
            'contact_name' => [
                'nullable',
            ],
            'website' => [
                'nullable',
            ],
            'prefix' => [
                'nullable',
            ],
            'tax_id' => [
                'nullable',
            ],
            'enable_portal' => [
                'boolean',
            ],
            'currency_id' => [
                'nullable',
            ],
            'billing.name' => [
                'nullable',
            ],
            'billing.address_street_1' => [
                'nullable',
            ],
            'billing.address_street_2' => [
                'nullable',
            ],
            'billing.city' => [
                'nullable',
            ],
            'billing.state' => [
                'nullable',
            ],
            'billing.country_id' => [
                'nullable',
            ],
            'billing.zip' => [
                'nullable',
            ],
            'billing.phone' => [
                'nullable',
            ],
            'billing.fax' => [
                'nullable',
            ],
            'shipping.name' => [
                'nullable',
            ],
            'shipping.address_street_1' => [
                'nullable',
            ],
            'shipping.address_street_2' => [
                'nullable',
            ],
            'shipping.city' => [
                'nullable',
            ],
            'shipping.state' => [
                'nullable',
            ],
            'shipping.country_id' => [
                'nullable',
            ],
            'shipping.zip' => [
                'nullable',
            ],
            'shipping.phone' => [
                'nullable',
            ],
            'shipping.fax' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT') && $this->email != null) {
            $rules['email'] = [
                new IdnEmail,
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company'))->ignore($this->route('customer')->id),
            ];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function customerAttributes(): array
    {
        return collect($this->validated())
            ->only([
                'name',
                'email',
                'currency_id',
                'password',
                'phone',
                'prefix',
                'tax_id',
                'company_name',
                'contact_name',
                'website',
                'enable_portal',
                'estimate_prefix',
                'payment_prefix',
                'invoice_prefix',
            ])
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }

    /** @return array<string, mixed>|null */
    public function shippingAddress(): ?array
    {
        $address = $this->input('shipping');

        if (! is_array($address) || ! $this->hasAddress($address)) {
            return null;
        }

        return collect($address)
            ->merge([
                'type' => Address::SHIPPING_TYPE,
            ])
            ->toArray();
    }

    /** @return array<string, mixed>|null */
    public function billingAddress(): ?array
    {
        $address = $this->input('billing');

        if (! is_array($address) || ! $this->hasAddress($address)) {
            return null;
        }

        return collect($address)
            ->merge([
                'type' => Address::BILLING_TYPE,
            ])
            ->toArray();
    }

    /** @return array<int, mixed>|null */
    public function customFields(): ?array
    {
        $customFields = $this->input('customFields');

        return is_array($customFields) && $customFields !== [] ? $customFields : null;
    }

    private function hasAddress(array $address): bool
    {
        return Arr::where($address, fn ($value): bool => isset($value)) !== [];
    }
}

<?php

namespace App\Domains\Contacts\Http\Requests\CustomerPortal;

use App\Domains\Contacts\Models\Address;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerProfileRequest extends FormRequest
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
        return [
            'name' => [
                'nullable',
            ],
            'password' => [
                'nullable',
                'min:8',
            ],
            'email' => [
                'nullable',
                new IdnEmail,
                Rule::unique('customers')->where('company_id', $this->header('company'))->ignore(Auth::id(), 'id'),
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
            'customer_avatar' => [
                'nullable',
                'file',
                'mimes:gif,jpg,png',
                'max:20000',
            ],
            'is_customer_avatar_removed' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function customerAttributes(): array
    {
        return $this->safe()->only(['name', 'email', 'password']);
    }

    /** @return array<string, mixed>|null */
    public function shippingAddress(): ?array
    {
        $address = $this->input('shipping');

        if (! is_array($address)) {
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

        if (! is_array($address)) {
            return null;
        }

        return collect($address)
            ->merge([
                'type' => Address::BILLING_TYPE,
            ])
            ->toArray();
    }
}

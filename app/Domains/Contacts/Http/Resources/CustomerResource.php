<?php

namespace App\Domains\Contacts\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'company_name' => $this->company_name,
            'website' => $this->website,
            'enable_portal' => $this->enable_portal,
            'password_added' => $this->password ? true : false,
            'currency_id' => $this->currency_id,
            'company_id' => $this->company_id,
            'facebook_id' => $this->facebook_id,
            'google_id' => $this->google_id,
            'github_id' => $this->github_id,
            'created_at' => $this->created_at,
            'formatted_created_at' => $this->formattedCreatedAt,
            'updated_at' => $this->updated_at,
            'avatar' => $this->avatar,
            'due_amount' => $this->due_amount,
            'base_due_amount' => $this->base_due_amount,
            'invoice_due_amount' => $this->invoice_due_amount,
            'base_invoice_due_amount' => $this->base_invoice_due_amount,
            'available_credit' => $this->available_credit,
            'base_available_credit' => $this->base_available_credit,
            'account_balance' => $this->account_balance,
            'base_account_balance' => $this->base_account_balance,
            'prefix' => $this->prefix,
            'tax_id' => $this->tax_id,
            'billing' => $this->when($this->billingAddress()->exists(), function () {
                return new AddressResource($this->billingAddress);
            }),
            'shipping' => $this->when($this->shippingAddress()->exists(), function () {
                return new AddressResource($this->shippingAddress);
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
        ];
    }
}

<?php

namespace App\Domains\Accounts\Models;

use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\CustomFieldValue;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Silber\Bouncer\Database\Role;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Company extends Model implements HasMedia
{
    protected $table = 'companies';

    use HasFactory;
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->useDisk('public')
            ->singleFile();
    }

    protected $guarded = [
        'id',
    ];

    protected $appends = ['logo', 'logo_path'];

    public function getRolesAttribute()
    {
        return Role::where('scope', $this->id)
            ->get();
    }

    public function getLogoPathAttribute()
    {
        $logo = $this->getMedia('logo')->first();

        $isSystem = FileDisk::whereSetAsDefault(true)->first()->isSystem();

        if ($logo) {
            if ($isSystem) {
                return $logo->getPath();
            } else {
                return $logo->getFullUrl();
            }
        }

        return null;
    }

    public function getLogoAttribute()
    {
        $logo = $this->getMedia('logo')->first();

        if ($logo) {
            return $logo->getFullUrl();
        }

        return null;
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function exchangeRateLogs(): HasMany
    {
        return $this->hasMany(ExchangeRateLog::class);
    }

    public function exchangeRateProviders(): HasMany
    {
        return $this->hasMany(ExchangeRateProvider::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function taxTypes(): HasMany
    {
        return $this->hasMany(TaxType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company', 'company_id', 'user_id');
    }

    /**
     * Check whether the company has any business data such as customers,
     * items, invoices, estimates, expenses, payments, or recurring invoices.
     */
    public function hasTransactions(): bool
    {
        if (
            $this->customers()->exists() ||
            $this->items()->exists() ||
            $this->invoices()->exists() ||
            $this->estimates()->exists() ||
            $this->expenses()->exists() ||
            $this->payments()->exists() ||
            $this->recurringInvoices()->exists()
        ) {
            return true;
        }

        return false;
    }
}

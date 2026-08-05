<?php

namespace App\Domains\Catalog\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Catalog\Contracts\ItemTaxManager;
use App\Domains\Catalog\Models\Item;

class ItemService
{
    public function __construct(
        private readonly ItemTaxManager $itemTaxManager,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $taxes
     */
    public function create(array $attributes, array $taxes, int $companyId, int $creatorId): Item
    {
        $attributes['company_id'] = $companyId;
        $attributes['creator_id'] = $creatorId;
        $attributes['currency_id'] = CompanySetting::getSetting('currency', $companyId);

        $item = Item::create($attributes);

        $this->itemTaxManager->attach($item, $taxes, $companyId);

        return Item::query()->with('taxes')->findOrFail($item->getKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $taxes
     */
    public function update(Item $item, array $attributes, array $taxes, int $companyId): Item
    {
        $item->update($attributes);

        $this->itemTaxManager->replace($item, $taxes, $companyId);

        return Item::query()->with('taxes')->findOrFail($item->getKey());
    }
}

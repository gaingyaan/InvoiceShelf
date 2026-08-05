<?php

namespace App\Domains\Contacts\Contracts;

use App\Domains\Contacts\Models\Customer;

interface CustomerStatsProvider
{
    /** @return array<string, mixed> */
    public function get(Customer $customer, int $companyId, bool $previousYear = false): array;
}

<?php

namespace App\Domains\Metadata\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CustomFieldValueWriter
{
    public function attach(Model $valuable, iterable $customFields): void;

    public function update(Model $valuable, iterable $customFields): void;
}

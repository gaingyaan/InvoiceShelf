<?php

namespace App\Platform\Mail\Contracts;

interface MailConfigurator
{
    public function applyGlobalConfig(): void;

    public function applyCompanyConfig(int|string $companyId): void;
}

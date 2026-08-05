<?php

declare(strict_types=1);

namespace App\Support\Hashids;

enum HashidConnection: string
{
    case Company = 'company';
    case EmailLog = 'email_log';
    case Estimate = 'estimate';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Transaction = 'transaction';
}

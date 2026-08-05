<?php

namespace App\Domains\Money\Http\Controllers;

use App\Domains\Money\Application\CurrencyService;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrenciesController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $currencies = $this->currencyService->getAllWithCommonFirst();

        return CurrencyResource::collection($currencies);
    }
}

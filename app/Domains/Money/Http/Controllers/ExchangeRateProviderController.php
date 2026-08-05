<?php

namespace App\Domains\Money\Http\Controllers;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Application\ExchangeRateProviderService;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Money\ExchangeRates\ExchangeRateException;
use App\Domains\Money\Http\Requests\BulkExchangeRateRequest;
use App\Domains\Money\Http\Requests\ExchangeRateProviderRequest;
use App\Domains\Money\Http\Resources\ExchangeRateProviderResource;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ExchangeRateProviderController extends Controller
{
    public function __construct(
        private readonly ExchangeRateProviderService $exchangeRateProviderService,
        private readonly ExchangeRateBackfill $exchangeRateBackfill,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $exchangeRateProviders = ExchangeRateProvider::whereCompany()->paginate($limit);

        return ExchangeRateProviderResource::collection($exchangeRateProviders);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(ExchangeRateProviderRequest $request)
    {
        $this->authorize('create', ExchangeRateProvider::class);

        $payload = $request->getExchangeRateProviderPayload();
        $query = $this->exchangeRateProviderService->checkActiveCurrencies($payload['currencies'] ?? []);

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        try {
            $this->exchangeRateProviderService->validateProvider($payload);
            $exchangeRateProvider = $this->exchangeRateProviderService->create($payload);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('view', $exchangeRateProvider);

        return new ExchangeRateProviderResource($exchangeRateProvider);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(ExchangeRateProviderRequest $request, ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('update', $exchangeRateProvider);

        $payload = $request->getExchangeRateProviderPayload();
        $query = $this->exchangeRateProviderService->checkUpdateActiveCurrencies(
            $exchangeRateProvider,
            $payload['currencies'] ?? [],
        );

        if (count($query) !== 0) {
            return respondJson('currency_used', 'Currency used.');
        }

        try {
            $this->exchangeRateProviderService->validateProvider($payload);
            $this->exchangeRateProviderService->update($exchangeRateProvider, $payload);

            return new ExchangeRateProviderResource($exchangeRateProvider);
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(ExchangeRateProvider $exchangeRateProvider)
    {
        $this->authorize('delete', $exchangeRateProvider);

        if ($exchangeRateProvider->active == true) {
            return respondJson('provider_active', 'Provider Active.');
        }

        $exchangeRateProvider->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function activeProvider(Request $request, Currency $currency)
    {
        $query = ExchangeRateProvider::whereCompany()->whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get();

        if (count($query) !== 0) {
            return response()->json([
                'success' => true,
                'message' => 'provider_active',
            ], 200);
        }

        return response()->json([
            'error' => 'no_active_provider',
        ], 200);
    }

    public function getRate(Request $request, Currency $currency)
    {
        $settings = CompanySetting::getSettings(['currency'], $request->header('company'));
        $baseCurrency = Currency::findOrFail($settings['currency']);

        $query = ExchangeRateProvider::whereJsonContains('currencies', $currency->code)
            ->where('active', true)
            ->get()
            ->toArray();

        $exchangeRate = ExchangeRateLog::where('base_currency_id', $currency->id)
            ->where('currency_id', $baseCurrency->id)
            ->orderBy('created_at', 'desc')
            ->value('exchange_rate');

        if ($query) {
            $filter = Arr::only($query[0], ['key', 'driver', 'driver_config']);
            try {
                $exchangeRate = $this->exchangeRateProviderService->getExchangeRate(
                    $filter['driver'],
                    $filter['key'],
                    $filter['driver_config'] ?? [],
                    $currency->code,
                    $baseCurrency->code,
                );

                return response()->json(['exchangeRate' => $exchangeRate]);
            } catch (ExchangeRateException) {
                // Fall back to the latest stored rate below, matching the
                // existing API behavior when a live provider is unavailable.
            }
        }
        if ($exchangeRate) {
            return response()->json([
                'exchangeRate' => [$exchangeRate],
            ], 200);
        }

        return response()->json([
            'error' => 'no_exchange_rate_available',
        ], 200);
    }

    public function supportedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        try {
            $currencies = $this->exchangeRateProviderService->getSupportedCurrencies(
                $request->driver,
                $request->key,
                $request->driver_config ?? [],
            );

            return response()->json(['supportedCurrencies' => $currencies]);
        } catch (ExchangeRateException $exception) {
            return respondJson($exception->errorKey, $exception->getMessage());
        }
    }

    public function usedCurrencies(Request $request)
    {
        $this->authorize('viewAny', ExchangeRateProvider::class);

        $providerId = $request->provider_id;

        $activeExchangeRateProviders = ExchangeRateProvider::where('active', true)
            ->whereCompany()
            ->when($providerId, function ($query) use ($providerId) {
                return $query->where('id', '<>', $providerId);
            })
            ->pluck('currencies');
        $activeExchangeRateProvider = [];

        foreach ($activeExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $activeExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        $allExchangeRateProviders = ExchangeRateProvider::whereCompany()->pluck('currencies');
        $allExchangeRateProvider = [];

        foreach ($allExchangeRateProviders as $data) {
            if (is_array($data)) {
                for ($limit = 0; $limit < count($data); $limit++) {
                    $allExchangeRateProvider[] = $data[$limit];
                }
            }
        }

        return response()->json([
            'allUsedCurrencies' => $allExchangeRateProvider ? $allExchangeRateProvider : [],
            'activeUsedCurrencies' => $activeExchangeRateProvider ? $activeExchangeRateProvider : [],
        ]);
    }

    public function usedCurrenciesWithoutRate(Request $request)
    {
        return response()->json([
            'currencies' => Currency::whereIn(
                'id',
                $this->exchangeRateBackfill->currencyIdsMissingRates(),
            )->get(),
        ]);
    }

    public function bulkUpdate(BulkExchangeRateRequest $request)
    {
        if ($this->exchangeRateBackfill->apply(
            (int) $request->header('company'),
            $request->validated('currencies'),
        )) {
            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'error' => false,
        ]);
    }
}

<?php

namespace App\Domains\Contacts\Http\Controllers;

use App\Domains\Contacts\Http\Resources\CountryResource;
use App\Domains\Contacts\Models\Country;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountriesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $countries = Country::all();

        return CountryResource::collection($countries);
    }
}

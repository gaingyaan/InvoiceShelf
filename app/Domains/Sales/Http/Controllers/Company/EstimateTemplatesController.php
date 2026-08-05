<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstimateTemplatesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $estimateTemplates = PdfTemplateUtils::getFormattedTemplates('estimate');

        return response()->json([
            'estimateTemplates' => $estimateTemplates,
        ]);
    }
}

<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTemplatesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $invoiceTemplates = PdfTemplateUtils::getFormattedTemplates('invoice');

        return response()->json([
            'invoiceTemplates' => $invoiceTemplates,
        ]);
    }
}

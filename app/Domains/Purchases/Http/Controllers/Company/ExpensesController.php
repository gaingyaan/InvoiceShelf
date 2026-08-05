<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\ExpenseService;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Http\Requests\DeleteExpensesRequest;
use App\Domains\Purchases\Http\Requests\ExpenseRequest;
use App\Domains\Purchases\Http\Requests\UploadExpenseReceiptRequest;
use App\Domains\Purchases\Http\Resources\ExpenseResource;
use App\Domains\Purchases\Models\Expense;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpensesController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpenseReceiptManager $expenseReceiptManager,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $expenses = Expense::with('category', 'creator', 'fields')
            ->whereCompany()
            ->leftJoin('customers', 'customers.id', '=', 'expenses.customer_id')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->applyFilters($request->all())
            ->select('expenses.*', 'expense_categories.name', 'customers.name as user_name')
            ->paginateData($limit);

        return ExpenseResource::collection($expenses)
            ->additional(['meta' => [
                'expense_total_count' => Expense::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(ExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);

        $expense = $this->expenseService->create(
            attributes: $request->getExpensePayload(),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            receipt: $this->receipt($request),
            customFields: $this->customFields($request),
        );

        return new ExpenseResource($expense);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        $expense->load('taxes.taxType');

        return new ExpenseResource($expense);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(ExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $expense = $this->expenseService->update(
            expense: $expense,
            attributes: $request->getExpensePayload(),
            taxes: $request->has('taxes') ? $request->input('taxes') : null,
            receipt: $this->receipt($request),
            removeReceipt: (bool) $request->input('is_attachment_receipt_removed', false),
            customFields: $this->customFields($request),
        );

        return new ExpenseResource($expense);
    }

    public function delete(DeleteExpensesRequest $request)
    {
        $this->authorize('delete multiple expenses');

        $ids = Expense::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        Expense::destroy($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    public function showReceipt(Expense $expense)
    {
        $this->authorize('view', $expense);

        $receipt = $this->expenseReceiptManager->first($expense);

        if ($receipt) {
            return response()->file($receipt->path);
        }

        return respondJson('receipt_does_not_exist', 'Receipt does not exist.');
    }

    public function uploadReceipt(UploadExpenseReceiptRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $data = json_decode($request->attachment_receipt);

        if ($data) {
            $this->expenseReceiptManager->attachBase64(
                $expense,
                $data->data,
                $data->name,
                $request->type === 'edit',
            );
        }

        return response()->json([
            'success' => 'Expense receipts uploaded successfully',
        ], 200);
    }

    public function downloadReceipt(Expense $expense)
    {
        $this->authorize('view', $expense);

        $receipt = $this->expenseReceiptManager->first($expense);

        if ($receipt) {
            $response = response()->download($receipt->path, $receipt->fileName);
            if (ob_get_contents()) {
                ob_end_clean();
            }

            return $response;
        }

        return response()->json([
            'error' => 'receipt_not_found',
        ]);
    }

    /** @return array<int, mixed>|null */
    private function customFields(ExpenseRequest $request): ?array
    {
        $customFields = $request->input('customFields');

        if (! $customFields) {
            return null;
        }

        if (is_string($customFields)) {
            $customFields = json_decode($customFields);
        }

        return is_array($customFields) ? $customFields : null;
    }

    private function receipt(ExpenseRequest $request): ?PendingExpenseReceipt
    {
        $receipt = $request->file('attachment_receipt');

        if (! $receipt) {
            return null;
        }

        return new PendingExpenseReceipt(
            $receipt->getPathname(),
            $receipt->getClientOriginalName(),
        );
    }
}

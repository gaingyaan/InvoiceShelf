<?php

namespace App\Http\Controllers\V1\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];

        $i = 0;
        $months = [];
        $monthCounter = 0;
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = intval($terms[0]);

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
            $end->month($companyStartMonth)->endOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
            $end->subYear()->month($companyStartMonth)->endOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        $rangeStart = $startDate->copy()->startOfMonth();
        $rangeEnd = $startDate->copy()->addMonths(11)->endOfMonth();

        $driver = DB::connection()->getDriverName();
        $dateFormatExpr = function (string $column) use ($driver) {
            return match ($driver) {
                'sqlite' => "strftime('%Y-%m', {$column})",
                'pgsql' => "to_char({$column}, 'YYYY-MM')",
                'sqlsrv' => "FORMAT({$column}, 'yyyy-MM')",
                default => "DATE_FORMAT({$column}, '%Y-%m')",
            };
        };

        $invoicesByMonth = Invoice::selectRaw("{$dateFormatExpr('invoice_date')} as month_key, sum(base_total) as total")
            ->whereBetween('invoice_date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->whereCompany()
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $expensesByMonth = Expense::selectRaw("{$dateFormatExpr('expense_date')} as month_key, sum(base_amount) as total")
            ->whereBetween('expense_date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->whereCompany()
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $receiptsByMonth = Payment::selectRaw("{$dateFormatExpr('payment_date')} as month_key, sum(base_amount) as total")
            ->whereBetween('payment_date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->whereCompany()
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $currentMonth = $startDate->copy();
        for ($monthCounter = 0; $monthCounter < 12; $monthCounter++) {
            $monthKey = $currentMonth->format('Y-m');
            $months[] = $currentMonth->translatedFormat('M');

            $invoiceTotal = (int) ($invoicesByMonth->get($monthKey) ?? 0);
            $expenseTotal = (int) ($expensesByMonth->get($monthKey) ?? 0);
            $receiptTotal = (int) ($receiptsByMonth->get($monthKey) ?? 0);

            $invoice_totals[] = $invoiceTotal;
            $expense_totals[] = $expenseTotal;
            $receipt_totals[] = $receiptTotal;
            $net_income_totals[] = $receiptTotal - $expenseTotal;

            $currentMonth->addMonth();
        }

        $total_sales = array_sum($invoice_totals);
        $total_receipts = array_sum($receipt_totals);
        $total_expenses = array_sum($expense_totals);
        $total_net_income = (int) $total_receipts - (int) $total_expenses;

        $chart_data = [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
        ];

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        $total_amount_due = Invoice::whereCompany()
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
        ]);
    }
}

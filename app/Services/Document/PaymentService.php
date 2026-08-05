<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Facades\Pdf;
use App\Mail\SendPaymentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Mail\CompanyMailConfigService;
use App\Support\Pdf\PdfMetadata;
use App\Support\Pdf\PdfTemplateUtils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PaymentAllocationService $paymentAllocationService,
    ) {}

    public function create(Request $request): Payment
    {
        $data = $request->getPaymentPayload();
        $allocations = $request->validated('allocations') ?? [];

        $payment = DB::transaction(function () use ($data, $allocations, $request): Payment {
            $payment = Payment::create($data);
            $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);

            $serial = (new SerialNumberService)
                ->setModel($payment)
                ->setCompany($payment->company_id)
                ->setCustomer($payment->customer_id)
                ->setNextNumbers();

            $payment->sequence_number = $serial->nextSequenceNumber;
            $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $payment->save();

            $this->paymentAllocationService->replace($payment, $allocations);

            $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

            if ((string) $payment->currency_id !== $companyCurrency) {
                ExchangeRateLog::addExchangeRateLog($payment);
            }

            if ($request->customFields) {
                $payment->addCustomFields($request->customFields);
            }

            return $payment;
        });

        return $this->loadPayment($payment);
    }

    public function update(Payment $payment, Request $request): Payment
    {
        $data = $request->getPaymentPayload();
        $replaceAllocations = $request->exists('allocations');
        $requestedAllocations = $request->validated('allocations') ?? [];

        $payment = DB::transaction(function () use ($payment, $data, $replaceAllocations, $requestedAllocations, $request): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $allocations = $replaceAllocations
                ? $requestedAllocations
                : $lockedPayment->allocations()
                    ->get(['invoice_id', 'amount'])
                    ->map(fn ($allocation) => [
                        'invoice_id' => (int) $allocation->invoice_id,
                        'amount' => (int) $allocation->amount,
                    ])
                    ->all();
            $customerChanged = (int) $lockedPayment->customer_id !== (int) $data['customer_id'];

            if ($customerChanged && $allocations !== []) {
                throw ValidationException::withMessages([
                    'customer_id' => ['payment_customer_change_requires_unallocated_credit'],
                ]);
            }

            $serial = (new SerialNumberService)
                ->setModel($lockedPayment)
                ->setCompany($lockedPayment->company_id)
                ->setCustomer($data['customer_id'])
                ->setModelObject($lockedPayment->id)
                ->setNextNumbers();

            $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
            $lockedPayment->update($data);
            $this->paymentAllocationService->replace($lockedPayment, $allocations);

            $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

            if ((string) $lockedPayment->currency_id !== $companyCurrency) {
                ExchangeRateLog::addExchangeRateLog($lockedPayment);
            }

            if ($request->customFields) {
                $lockedPayment->updateCustomFields($request->customFields);
            }

            return $lockedPayment;
        });

        return $this->loadPayment($payment);
    }

    public function delete(Collection $ids): bool
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids->sort() as $id) {
                $payment = Payment::query()->whereKey($id)->lockForUpdate()->first();

                if (! $payment) {
                    continue;
                }

                $this->paymentAllocationService->replace($payment, []);
                $payment->delete();
            }
        });

        return true;
    }

    public function sendPaymentData(Payment $payment, array $data): array
    {
        $data['payment'] = $payment->toArray();
        $data['user'] = $payment->customer->toArray();
        $data['company'] = Company::find($payment->company_id);
        $data['body'] = $payment->getEmailBody($data['body']);
        $data['attach']['data'] = ($payment->getEmailAttachmentSetting()) ? $this->getPdfData($payment) : null;

        return $data;
    }

    public function send(Payment $payment, array $data): array
    {
        $data = $this->sendPaymentData($payment, $data);

        CompanyMailConfigService::apply($payment->company_id);

        $mail = \Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }
        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }
        $mail->send(new SendPaymentMail($data));

        return [
            'success' => true,
        ];
    }

    public function getPdfData(Payment $payment)
    {
        $payment->loadMissing('allocations.invoice.currency');

        $company = Company::find($payment->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        \App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'payment' => $payment,
            'company_address' => $payment->getCompanyAddress(),
            'billing_address' => $payment->getCustomerBillingAddress(),
            'notes' => $payment->getNotes(),
            'logo' => $logo ?? null,
        ]);

        $templatePath = PdfTemplateUtils::resolveView('payment', 'payment');

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath, PdfMetadata::forDocument(
            __('pdf_payment_label'),
            $payment->payment_number,
            $company,
        ));
    }

    public function generateFromTransaction($transaction): Payment
    {
        $invoice = Invoice::find($transaction->invoice_id);

        $serial = (new SerialNumberService)
            ->setModel(new Payment)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $data['payment_number'] = $serial->getNextNumber();
        $data['payment_date'] = Carbon::now();
        $data['amount'] = $invoice->due_amount;
        $data['payment_method_id'] = request()->payment_method_id;
        $data['customer_id'] = $invoice->customer_id;
        $data['exchange_rate'] = $invoice->exchange_rate;
        $data['base_amount'] = (int) round($data['amount'] * $data['exchange_rate']);
        $data['currency_id'] = $invoice->currency_id;
        $data['company_id'] = $invoice->company_id;
        $data['transaction_id'] = $transaction->id;

        return DB::transaction(function () use ($data, $serial, $invoice): Payment {
            $payment = Payment::create($data);
            $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
            $payment->sequence_number = $serial->nextSequenceNumber;
            $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $payment->save();

            $this->paymentAllocationService->replace($payment, [[
                'invoice_id' => $invoice->id,
                'amount' => (int) $data['amount'],
            ]]);

            return $payment;
        });
    }

    private function loadPayment(Payment $payment): Payment
    {
        return Payment::with([
            'customer',
            'allocations.invoice',
            'paymentMethod',
            'fields',
        ])->findOrFail($payment->id);
    }
}

<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Http\Resources\EstimateResource;
use App\Domains\Sales\Mail\EstimateViewedMail;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Http\Request;

class EstimatePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $estimate = $emailLog->mailable;
        abort_unless($estimate instanceof Estimate, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        if ($estimate->status == Estimate::STATUS_SENT || $estimate->status == Estimate::STATUS_DRAFT) {
            $estimate->status = Estimate::STATUS_VIEWED;
            $estimate->save();
            $notifyEstimateViewed = CompanySetting::getSetting(
                'notify_estimate_viewed',
                $estimate->company_id
            );

            if ($notifyEstimateViewed == 'YES') {
                $data['estimate'] = Estimate::findOrFail($estimate->id)->toArray();
                $data['user'] = Customer::find($estimate->customer_id)->toArray();
                $notificationEmail = CompanySetting::getSetting(
                    'notification_email',
                    $estimate->company_id
                );

                \Mail::to($notificationEmail)->send(new EstimateViewedMail($data));
            }
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    public function getEstimate(EmailLog $emailLog)
    {
        $estimate = $emailLog->mailable;
        abort_unless($estimate instanceof Estimate, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return new EstimateResource($estimate);
    }
}

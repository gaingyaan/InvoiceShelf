<?php

namespace App\Domains\Sales\Mail;

use App\Domains\Sales\Models\Estimate;
use App\Facades\Hashids;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use App\Support\Hashids\HashidConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendEstimateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $log = EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
            'cc' => $this->data['cc'] ?? null,
            'bcc' => $this->data['bcc'] ?? null,
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => ModelIdentityMap::aliasFor(Estimate::class),
            'mailable_id' => $this->data['estimate']['id'],
        ]);

        $log->token = Hashids::connection(HashidConnection::EmailLog->value)->encode($log->id);
        $log->save();

        $this->data['url'] = route('estimate', ['email_log' => $log->token]);

        $mailContent = $this->from($this->data['from'], config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown('emails.send.estimate', ['data', $this->data]);

        if ($this->data['attach']['data']) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['estimate']['estimate_number'].'.pdf'
            );
        }

        return $mailContent;
    }
}

<?php

namespace App\Platform\Mail\Application;

use App\Facades\Hashids;
use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use App\Support\Hashids\HashidConnection;
use Illuminate\Database\Eloquent\Model;

class EloquentEmailLogWriter implements EmailLogWriter
{
    public function record(Model $mailable, array $message): string
    {
        $log = EmailLog::create([
            ...$message,
            'mailable_type' => ModelIdentityMap::aliasFor($mailable::class),
            'mailable_id' => $mailable->getKey(),
        ]);

        $log->token = Hashids::connection(HashidConnection::EmailLog->value)->encode($log->id);
        $log->save();

        return $log->token;
    }
}

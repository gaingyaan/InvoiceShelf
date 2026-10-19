<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileDiskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'driver' => $this->driver,
            'set_as_default' => $this->set_as_default,
            'credentials' => $this->when($this->credentials, function () {
                $creds = $this->credentials;
                $sensitiveKeys = ['secret', 'key', 'token', 'password'];
                foreach ($creds as $k => $v) {
                    foreach ($sensitiveKeys as $sensitive) {
                        if (stripos($k, $sensitive) !== false && ! empty($v)) {
                            $creds[$k] = str_repeat('•', 8);
                        }
                    }
                }

                return $creds;
            }),
            'company_id' => $this->company_id,
        ];
    }
}

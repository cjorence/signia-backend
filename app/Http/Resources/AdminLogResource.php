<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Older sign-in entries already recorded their IP in this exact format.
        $ip = $this->ip_address;
        $action = $this->action;
        if (!$ip && preg_match('/^Successful admin session login from IP (\S+)$/D', $action, $match)
            && filter_var($match[1], FILTER_VALIDATE_IP)) {
            $ip = $match[1];
            $action = 'Signed in to the admin account';
        }

        return [
            'id' => $this->id,
            'admin_id' => $this->admin_id,
            'admin' => new UserResource($this->whenLoaded('admin')),
            'admin_name' => $this->admin_name,
            'ip_address' => $ip,
            'action' => $action,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

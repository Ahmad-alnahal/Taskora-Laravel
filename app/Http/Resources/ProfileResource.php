<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'hourly_rate' => (float) $this->hourly_rate,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'member_since' => $this->created_at->toDateString(),
            'stats' => [
                'projects_count' => $this->projects_count,
                'total_hours' => (float) $this->total_hours,
                'total_earnings' => (float) $this->total_earnings,
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Participant $resource
 */
class ParticipantResource extends JsonResource
{
    public static $wrap = 'data';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'external_id' => $this->resource->external_id,
            'kind' => $this->resource->kind,
            'name' => $this->resource->name,
            'avatar_url' => $this->resource->avatar_url,
            'role' => $this->resource->role,
            'is_operator' => (bool) $this->resource->is_operator,
        ];
    }
}

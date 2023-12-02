<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'images'        => json_decode($this->images, true),
            'coordinates'   => json_decode($this->coordinates, true),
            'metro'         => $this->metro,
            'city'          => $this->city,
            'area'          => $this->area,
            'region'        => $this->region,
            'street'        => $this->street,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $form = $this->form;
        $form = is_string($form) ? json_decode($form, true) : $form;
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'form'      => $form,
        ];
    }
}

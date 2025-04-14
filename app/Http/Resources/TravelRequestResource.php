<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'destination' => $this->resource->destination,
            'departure_date' => $this->resource->departure_date->format('Y-m-d'),
            'return_date' => $this->resource->return_date->format('Y-m-d'),
            'status' => $this->resource->status,

            'user' => [
                // Usando 'whenLoaded' para evitar erros de carregamento
                'id' => $this->whenLoaded('user', fn() => $this->resource->user->id),
                'name' => $this->whenLoaded('user', fn() => $this->resource->user->name),
                'email' => $this->whenLoaded('user', fn() => $this->resource->user->email),
            ],
            
            // Usa 'when' para incluir o campo apenas se não for nulo
            // ali em optional (optional helper) : formata se não for nulo
            'approved_at' => $this->when($this->resource->approved_at, optional($this->resource->approved_at)->format('Y-m-d H:i:s')),
            'canceled_at' => $this->when($this->resource->canceled_at, optional($this->resource->canceled_at)->format('Y-m-d H:i:s')),
            'created_at' => $this->resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->resource->updated_at->format('Y-m-d H:i:s'),
            'can_be_canceled' => $this->resource->canBeCanceled(),
        ];
    }
}

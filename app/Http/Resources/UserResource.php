<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'email' => $this->email,
            'cpf' => $this->cpf,

            // Novo campo adicionado
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),

            'matricula' => $this->matricula,

            // Enums: retornar o valor do enum
            'tipo' => $this->tipo?->value,

            'avatar_url' => $this->avatar_url
                ? Storage::url($this->avatar_url)
                : null,

            'fase' => $this->fase,

            'curso' => new CursoResource($this->whenLoaded('curso')),

            // Linha adicionada para o frontend conseguir filtrar por data
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
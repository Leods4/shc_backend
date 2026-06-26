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

            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'matricula' => $this->matricula,
            'tipo' => $this->tipo?->value,

            'avatar_url' => $this->avatar_url 
                ? url('/api/usuarios/avatars/' . basename($this->avatar_url)) 
                : null,

            'fase' => $this->fase,

            // A relação 'curso' agora é montada em formato de array anonimamente
            'curso' => $this->whenLoaded('curso', function () {
                return [
                    'id' => $this->curso->id,
                    'nome' => $this->curso->nome,
                    'horas_necessarias' => $this->curso->horas_necessarias,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
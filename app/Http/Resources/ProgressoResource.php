<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_horas_aprovadas' => $this->resource['total_horas_aprovadas'],
            'horas_necessarias' => $this->resource['horas_necessarias'],
            
            // Retorna as horas detalhadas por categoria
            'horas_por_categoria' => $this->resource['horas_por_categoria'] ?? [],
        ];
    }
}
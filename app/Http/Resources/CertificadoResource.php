<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // atualizado: agora pega o nome da categoria pelo relacionamento
            'categoria' => $this->categoria->nome ?? null,

            // se quiser retornar tudo:
            // 'categoria_dados' => new CategoriaResource($this->whenLoaded('categoria')),

            'nome_certificado' => $this->nome_certificado,
            'instituicao' => $this->instituicao,
            'carga_horaria_solicitada' => $this->carga_horaria_solicitada,
            'status' => $this->status, // Enum convertido automaticamente para string

            // Datas para o front
            'data_emissao' => $this->data_emissao->format('Y-m-d'),
            'data_emissao_formatada' => $this->data_emissao->format('d/m/Y'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),

            // URL pública do PDF
            'arquivo_url' => Storage::url($this->arquivo_url),

            // Campos da validação
            'horas_validadas' => $this->horas_validadas,
            'observacao' => $this->observacao,
            'data_validacao' => $this->data_validacao?->format('d/m/Y H:i'),

            // Relacionamentos
            'aluno' => new UserResource($this->whenLoaded('aluno')),
            'coordenador' => new UserResource($this->whenLoaded('coordenador')),
        ];
    }
}

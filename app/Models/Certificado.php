<?php

namespace App\Models;

use App\Enums\StatusCertificado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    use HasFactory;

    protected $fillable = [
        'aluno_id',
        'categoria_id', // atualizado
        'nome_certificado',
        'instituicao',
        'data_emissao',
        'carga_horaria_solicitada',
        'arquivo_url',
        'status',
        'coordenador_id',
        'horas_validadas',
        'observacao',
        'data_validacao',
    ];

    protected $casts = [
        'status' => StatusCertificado::class,
        'data_emissao' => 'date',
        'data_validacao' => 'datetime',
    ];

    /** Relacionamento: Certificado pertence a um Aluno */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aluno_id');
    }

    /** Relacionamento: Certificado é avaliado por um Coordenador */
    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordenador_id');
    }

    /** Novo relacionamento: Certificado pertence a uma Categoria */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}

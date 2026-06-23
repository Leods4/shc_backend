<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'horas_necessarias'];

    // Relacionamento: Curso tem muitos usuários (Alunos, Coordenadores)
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

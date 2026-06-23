<?php

namespace App\Models;

use App\Enums\TipoUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nome', 'email', 'cpf', 'data_nascimento',
        'matricula', 'password', 'tipo', 'avatar_url',
        'curso_id', 'fase',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'tipo' => TipoUsuario::class,
        'password' => 'hashed',
        'data_nascimento' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function (User $user) {
            if (empty($user->password) && !empty($user->data_nascimento)) {
                try {
                    $data = Carbon::parse($user->data_nascimento);
                    $user->password = $data->format('dmY');
                } catch (\Exception $e) {
                    // Fallback opcional
                }
            }
        });
    }

    // --------------------------------------------------------
    // Mutators (NOVO)
    // --------------------------------------------------------

    /**
     * Garante que o CPF seja sempre salvo com a máscara XXX.XXX.XXX-XX
     */
    public function setCpfAttribute($value)
    {
        // 1. Remove tudo que não for número para limpar a entrada
        $onlyNumbers = preg_replace('/\D/', '', $value);

        // 2. Se tiver 11 dígitos, aplica a formatação padrão
        if (strlen($onlyNumbers) === 11) {
            $this->attributes['cpf'] = preg_replace(
                '/(\d{3})(\d{3})(\d{3})(\d{2})/',
                '$1.$2.$3-$4',
                $onlyNumbers
            );
        } else {
            // Caso venha algo fora do padrão (ex: passaporte), salva como veio ou limpo
            $this->attributes['cpf'] = $value;
        }
    }

    // --------------------------------------------------------
    // Relacionamentos
    // --------------------------------------------------------

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function certificadosSubmetidos(): HasMany
    {
        return $this->hasMany(Certificado::class, 'aluno_id');
    }

    public function certificadosAvaliados(): HasMany
    {
        return $this->hasMany(Certificado::class, 'coordenador_id');
    }

    // ... (restante dos helpers mantidos igual) ...
    public function isAluno(): bool { return $this->tipo === TipoUsuario::ALUNO; }
    public function isCoordenador(): bool { return $this->tipo === TipoUsuario::COORDENADOR; }
    public function isSecretaria(): bool { return $this->tipo === TipoUsuario::SECRETARIA; }
    public function isAdmin(): bool { return $this->tipo === TipoUsuario::ADMINISTRADOR; }
}

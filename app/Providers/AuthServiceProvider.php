<?php

namespace App\Providers;

use App\Enums\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot(): void
    {
        // Gates de Perfil
        Gate::define('is-admin', fn (User $user) => $user->isAdmin());
        Gate::define('is-secretaria', fn (User $user) => $user->isSecretaria());
        Gate::define('is-coordenador', fn (User $user) => $user->isCoordenador());
        Gate::define('is-aluno', fn (User $user) => $user->isAluno());

        // Gates de Ação (ex: Secretaria OU Admin)
        Gate::define('manage-users', fn (User $user) => $user->isAdmin() || $user->isSecretaria());

        Gate::define('view-all-history', fn (User $user) => $user->isAdmin() || $user->isSecretaria());

        // Gates de Nível de Recurso (ex: Coordenador só pode avaliar aluno do seu curso)
        Gate::define('avaliar-certificado', function (User $coordenador, $certificado) {
            if (!$coordenador->isCoordenador()) {
                return false;
            }
            // Verifica se o aluno do certificado pertence ao mesmo curso do coordenador
            return $coordenador->curso_id === $certificado->aluno->curso_id;
        });

        Gate::define('view-progresso', function (User $user, $aluno) {
            // Admin/Secretaria podem ver qualquer progresso
            if ($user->isAdmin() || $user->isSecretaria()) {
                return true;
            }
            // Coordenador pode ver progresso de aluno do seu curso
            if ($user->isCoordenador() && $user->curso_id === $aluno->curso_id) {
                return true;
            }
            // Aluno só pode ver o seu próprio progresso
            return $user->id === $aluno->id;
        });
    }
}

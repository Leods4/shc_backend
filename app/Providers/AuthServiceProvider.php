<?php

namespace App\Providers;

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
        // --------------------------------------------------------
        // 1. Gates de Perfil (Roles)
        // --------------------------------------------------------
        Gate::define('is-admin', fn (User $user) => $user->isAdmin());
        Gate::define('is-secretaria', fn (User $user) => $user->isSecretaria());
        Gate::define('is-coordenador', fn (User $user) => $user->isCoordenador());
        Gate::define('is-aluno', fn (User $user) => $user->isAluno());

        // --------------------------------------------------------
        // 2. Gates de Ações Globais
        // --------------------------------------------------------
        Gate::define('manage-users', fn (User $user) => $user->isAdmin() || $user->isSecretaria());
        Gate::define('view-all-history', fn (User $user) => $user->isAdmin() || $user->isSecretaria());
        Gate::define('view-users', fn (User $user) => $user->isAdmin() || $user->isSecretaria() || $user->isCoordenador());

        // --------------------------------------------------------
        // 3. Gates Específicos de Recurso
        // --------------------------------------------------------
        Gate::define('view-progresso', function (User $user, $aluno) {
            if ($user->isAdmin() || $user->isSecretaria()) {
                return true;
            }
            if ($user->isCoordenador() && $user->curso_id === $aluno->curso_id) {
                return true;
            }
            return $user->id === $aluno->id;
        });

        Gate::define('avaliar-certificado', function (User $coordenador, $certificado) {
            if (!$coordenador->isCoordenador()) {
                return false;
            }
            return $coordenador->curso_id === $certificado->aluno->curso_id;
        });

        // Gates ausentes adicionados para proteger as rotas de PUT e DELETE de certificados
        Gate::define('update-certificado', function (User $user, $certificado) {
            // Apenas o aluno dono pode editar o certificado, e apenas se estiver ENTREGUE
            return $user->id === $certificado->aluno_id && $certificado->status->value === 'ENTREGUE';
        });

        Gate::define('delete-certificado', function (User $user, $certificado) {
            // Apenas o aluno dono pode deletar o certificado, e apenas se estiver ENTREGUE
            return $user->id === $certificado->aluno_id && $certificado->status->value === 'ENTREGUE';
        });

        Gate::define('view-certificado', function (User $user, $certificado) {
            if ($user->isAdmin() || $user->isSecretaria()) return true;
            if ($user->isCoordenador() && $user->curso_id === $certificado->aluno->curso_id) return true;
            return $user->id === $certificado->aluno_id;
        });
    }
}
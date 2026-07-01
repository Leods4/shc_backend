<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\CategoriaController;

// 1. Autenticação (Público)
Route::post('/auth/login', [AuthController::class, 'login']);

// 2. Rotas Protegidas (Requerem Token)
Route::middleware('auth:sanctum')->group(function () {

    // 2.1. Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    
    // (Rota para o *próprio* usuário logado)
    Route::post('/usuarios/avatar', [UsuarioController::class, 'updateAvatar']);
    // Visualizar Avatar (Qualquer usuário logado pode ver)
    Route::get('/usuarios/avatars/{filename}', [UsuarioController::class, 'showAvatar']);
    // Rota para eliminar o avatar do utilizador logado
    Route::delete('/usuarios/avatar', [UsuarioController::class, 'destroyAvatar']);

    // 2.2. Usuários (CRUD)
    
    // Rota para buscar o próprio usuário logado (Adicionado)
    Route::get('/usuarios/me', [UsuarioController::class, 'me']);

    // (Listar)
    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('can:view-users');
    // (Criar)
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('can:manage-users');

    // Restrição whereNumber('user') adicionada
    Route::prefix('usuarios/{user}')->whereNumber('user')->scopeBindings()->group(function () {
        // (Visualizar)
        Route::get('/', [UsuarioController::class, 'show'])->middleware('can:view-users');
        // (Atualizar)
        Route::put('/', [UsuarioController::class, 'update'])->middleware('can:manage-users');
        // (Remover)
        Route::delete('/', [UsuarioController::class, 'destroy'])->middleware('can:manage-users');
        // (Progresso do Aluno)
        Route::get('/progresso', [UsuarioController::class, 'getProgresso'])->middleware('can:view-progresso,user');
    });

    // 2.3. Certificados
    // (Listagem dinâmica baseada no perfil)
    Route::get('/certificados', [CertificadoController::class, 'index']);
    // (Aluno envia)
    Route::post('/certificados', [CertificadoController::class, 'store'])->middleware('can:is-aluno');

    // Rota para exportação geral (Movida para fora do grupo com ID)
    Route::get('/certificados/exportar/externo', [CertificadoController::class, 'export'])->middleware('can:is-admin');

    // Restrição whereNumber('certificado') adicionada
    Route::prefix('certificados/{certificado}')->whereNumber('certificado')->scopeBindings()->group(function () {
        Route::get('/', [CertificadoController::class, 'show'])->middleware('can:view-certificado,certificado');
        
        // --- ROTAS ADICIONADAS PARA COMPLETAR O CRUD DE CERTIFICADOS ---
        // (Atualização geral - Ex: aluno edita o envio antes de ser avaliado)
        Route::put('/', [CertificadoController::class, 'update'])->middleware('can:update-certificado,certificado');
        // (Exclusão - Ex: aluno cancela/deleta o envio errado)
        Route::delete('/', [CertificadoController::class, 'destroy'])->middleware('can:delete-certificado,certificado');
        // ---------------------------------------------------------------

        // (Coordenador avalia)
        Route::patch('/avaliar', [CertificadoController::class, 'avaliar'])->middleware('can:avaliar-certificado,certificado');

        // Visualizar PDF do Certificado (Protegido via ID do certificado)
        // CORREÇÃO: Removido o prefixo duplicado '/certificados/{certificado}'
        Route::get('/arquivo', [CertificadoController::class, 'showArquivo']);
    });

    // 2.4. Configurações (Admin)
    Route::get('/configuracoes', [ConfiguracaoController::class, 'index'])->middleware('can:is-admin');
    Route::put('/configuracoes', [ConfiguracaoController::class, 'update'])->middleware('can:is-admin');

    // 2.5. Cursos
    // (Leitura: Disponível para selects em formulários de qualquer usuário)
    Route::get('/cursos', [CursoController::class, 'index']);

    // (Gestão: Restrita ao Administrador)
    Route::post('/cursos', [CursoController::class, 'store'])->middleware('can:is-admin');

    // Restrição whereNumber('curso') adicionada
    Route::prefix('cursos/{curso}')->whereNumber('curso')->scopeBindings()->group(function () {
        Route::get('/', [CursoController::class, 'show']); // Ver detalhes
        Route::put('/', [CursoController::class, 'update'])->middleware('can:is-admin');
        Route::delete('/', [CursoController::class, 'destroy'])->middleware('can:is-admin');
    });

    // 2.6. Categorias
    // Listagem aberta para todos os usuários logados (para popular selects)
    Route::get('/categorias', [CategoriaController::class, 'index']);

    // Gestão restrita ao Administrador
    Route::post('/categorias', [CategoriaController::class, 'store'])->middleware('can:is-admin');
    
    // --- ROTA ADICIONADA PARA COMPLETAR O CRUD DE CATEGORIAS ---
    // Visualização de uma categoria específica (aberta como o index)
    Route::get('/categorias/{categoria}', [CategoriaController::class, 'show'])
        ->whereNumber('categoria');
    // -----------------------------------------------------------

    // Rota de Edição (Adicionada)
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])
        ->whereNumber('categoria')
        ->middleware('can:is-admin');

    // Restrição whereNumber('categoria') adicionada
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->whereNumber('categoria')->middleware('can:is-admin');

    // Rota Exclusiva de Importação (Admin)
    Route::post('/usuarios/import', [UsuarioController::class, 'import'])->middleware('can:is-admin');
});
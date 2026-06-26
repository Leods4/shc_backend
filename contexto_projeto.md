# Contexto do Projeto Laravel

Este arquivo contém a estrutura e o código-fonte principal da aplicação.

## Arquivo: app\Console\Commands\GenerateProjectContextCommand.php
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateProjectContextCommand extends Command
{
    protected $signature = 'app:bundle-context {--output=contexto_projeto.md}';
    protected $description = 'Consolida os arquivos principais do projeto em um único arquivo Markdown para contexto.';

    // Pastas e arquivos que queremos incluir
    protected $allowedDirectories = [
        'app',
        'config',
        'database/migrations',
        'database/seeders',
        'routes'
    ];

    // Extensões de arquivos permitidas
    protected $allowedExtensions = ['php', 'json'];

    public function handle()
    {
        $outputFile = $this->option('output');
        $content = "# Contexto do Projeto Laravel\n\n";
        $content .= "Este arquivo contém a estrutura e o código-fonte principal da aplicação.\n\n";

        foreach ($this->allowedDirectories as $dir) {
            $path = base_path($dir);

            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if (!in_array($file->getExtension(), $this->allowedExtensions)) {
                    continue;
                }

                $relativeSubPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                
                $this->info("Processando: {$relativeSubPath}");

                $content .= "## Arquivo: {$relativeSubPath}\n";
                $content .= "```{$file->getExtension()}\n";
                $content .= File::get($file->getRealPath()) . "\n";
                $content .= "```\n\n";
            }
        }

        File::put(base_path($outputFile), $content);
        $this->info("Sucesso! Contexto gerado em: " . base_path($outputFile));
    }
}
```

## Arquivo: app\Enums\StatusCertificado.php
```php
<?php

namespace App\Enums;

enum StatusCertificado: string
{
    case ENTREGUE = 'ENTREGUE';
    case APROVADO = 'APROVADO';
    case REPROVADO = 'REPROVADO';
    case APROVADO_COM_RESSALVAS = 'APROVADO_COM_RESSALVAS';
}

```

## Arquivo: app\Enums\TipoUsuario.php
```php
<?php

namespace App\Enums;

enum TipoUsuario: string
{
    case ALUNO = 'ALUNO';
    case COORDENADOR = 'COORDENADOR';
    case SECRETARIA = 'SECRETARIA';
    case ADMINISTRADOR = 'ADMINISTRADOR';
}

```

## Arquivo: app\Http\Controllers\AuthController.php
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthPayloadResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password; // Importante para a validação da senha

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validação absorvida do LoginRequest
        $request->validate([
            'cpf' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('cpf', $request->cpf)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'cpf' => ['CPF ou senha inválidos.'],
            ]);
        }

        // Revoga tokens antigos
        $user->tokens()->delete();

        $token = $user->createToken('shc-token')->plainTextToken;

        return new AuthPayloadResource($user->load('curso'), $token);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    public function changePassword(Request $request) 
    {
        $user = $request->user();

        // Validação absorvida do ChangePasswordRequest
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->noContent();
    }
}
```

## Arquivo: app\Http\Controllers\CategoriaController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Http\Resources\CategoriaResource;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Lista todas as categorias (Usado no dropdown de cadastro de horas)
     */
    public function index()
    {
        // Retorna ordenado alfabeticamente
        return CategoriaResource::collection(Categoria::orderBy('nome')->get());
    }

    /**
     * Cria uma nova categoria (Apenas Admin - Configurações)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:categorias,nome']
        ]);

        $categoria = Categoria::create(['nome' => $request->nome]);

        return new CategoriaResource($categoria);
    }

    /**
     * Exibe uma categoria específica
     */
    public function show(Categoria $categoria)
    {
        return new CategoriaResource($categoria);
    }

    /**
     * Atualiza uma categoria existente (Apenas Admin)
     */
    public function update(Request $request, Categoria $categoria)
    {
        $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:categorias,nome,' . $categoria->id],
        ]);

        $categoria->update(['nome' => $request->nome]);

        return new CategoriaResource($categoria);
    }

    /**
     * Remove uma categoria (Apenas Admin)
     */
    public function destroy(Categoria $categoria)
    {
        // Opcional: Verificar se existem certificados usando esta categoria antes de excluir
        // if ($categoria->certificados()->exists()) { ... erro ... }

        $categoria->delete();

        return response()->noContent();
    }
}
```

## Arquivo: app\Http\Controllers\CertificadoController.php
```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusCertificado;
use App\Models\Certificado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use App\Http\Resources\CertificadoResource;

class CertificadoController extends Controller
{
    /**
     * INDEX — Listagem com filtros por regras de permissão e filtros avançados
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Certificado::query()->with('aluno', 'coordenador', 'categoria');

        // FILTROS

        if ($request->filled('aluno_id')) {
            $query->where('aluno_id', $request->aluno_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;

            $query->whereHas('aluno', function ($q) use ($term) {
                $q->where('nome', 'like', "%{$term}%")
                    ->orWhere('cpf', 'like', "%{$term}%");
            });
        }

        if ($request->filled('data_inicio') && $request->filled('data_fim')) {
            $query->whereBetween('data_emissao', [
                $request->data_inicio,
                $request->data_fim,
            ]);
        }

        if (
            $request->filled('curso_id') &&
            ($user->isSecretaria() || $user->isAdmin())
        ) {
            $query->whereHas('aluno', function ($q) use ($request) {
                $q->where('curso_id', $request->curso_id);
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        // 1. ADICIONE O FILTRO GLOBAL AQUI:
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // REGRAS POR PAPEL

        if ($user->isAluno()) {

            $query->where('aluno_id', $user->id);

        } elseif ($user->isCoordenador()) {

            $query->whereHas('aluno', fn($q) =>
                $q->where('curso_id', $user->curso_id)
            );
        }

        return CertificadoResource::collection(
            $query->latest()->get()
        );
    }

    /**
     * STORE — Aluno envia certificado
     */
    public function store(Request $request)
    {
        // --- INÍCIO DA TRAVA DE MANUTENÇÃO ---
        $config = \App\Models\Configuracao::pluck('valor', 'chave')->toArray();
        $modoManutencao = $config['modo_manutencao'] ?? false;

        if ($modoManutencao === 'true' || $modoManutencao === '1' || $modoManutencao === true) 
            {
            return response()->json(
            [
                'message' => 'O sistema está em manutenção. O envio de novos certificados está temporariamente suspenso.'
            ], 403);
        }
        // --- FIM DA TRAVA ---

        $validated = $request->validate([
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'nome_certificado' => ['required', 'string', 'max:255'],
            'instituicao' => ['required', 'string', 'max:255'],
            'data_emissao' => ['required', 'date'],
            'carga_horaria_solicitada' => ['required', 'integer', 'min:1'],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('arquivo')->store('certificados', 'public');

        $certificado = Certificado::create([
            ...$validated,
            'arquivo_url' => $path,
            'aluno_id' => Auth::id(),
            'status' => StatusCertificado::ENTREGUE,
        ]);

        $certificado->load('categoria');

        return new CertificadoResource($certificado);
    }

    /**
     * AVALIAR — Coordenador aprova/reprova o certificado
     */
    public function avaliar(Certificado $certificado, Request $request)
    {
        $data = $request->validate([
            'status' => ['required', new Enum(StatusCertificado::class)],
            'horas_validadas' => [
                'required_if:status,APROVADO,APROVADO_COM_RESSALVAS',
                'nullable',
                'integer',
                'min:0'
            ],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'categoria_id' => ['sometimes', 'exists:categorias,id'],
            'nome_certificado' => ['sometimes', 'string', 'max:255'],
            'instituicao' => ['sometimes', 'string', 'max:255'],
            'data_emissao' => ['sometimes', 'date'],
            'carga_horaria_solicitada' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($data['status'] === StatusCertificado::REPROVADO->value) {
            $data['horas_validadas'] = 0;
        }

        $certificado->update([
            ...$data,
            'coordenador_id' => Auth::id(),
            'data_validacao' => now(),
        ]);

        $certificado->load('categoria');

        return new CertificadoResource($certificado);
    }

    /**
     * EXPORT — Exporta dados para um sistema externo
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        $query = Certificado::query()->with('aluno', 'coordenador', 'categoria');

        if ($request->filled('aluno_id')) {
            $query->where('aluno_id', $request->aluno_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('data_inicio') && $request->filled('data_fim')) {
            $query->whereBetween('data_emissao', [
                $request->data_inicio,
                $request->data_fim,
            ]);
        }

        if ($user->isAluno()) {

            $query->where('aluno_id', $user->id);

        } elseif ($user->isCoordenador()) {

            $query->whereHas('aluno', fn($q) =>
                $q->where('curso_id', $user->curso_id)
            );
        }

        $certificados = $query->latest()->get();

        return CertificadoResource::collection($certificados);
    }

    /**
     * SHOW — Detalhes de um certificado específico
     */
    public function show(Certificado $certificado)
    {
        $certificado->load('aluno', 'coordenador', 'categoria');

        return new CertificadoResource($certificado);
    }

    /**
     * UPDATE — Atualiza dados/arquivo do certificado
     */
    public function update(Request $request, Certificado $certificado)
    {
        if ($certificado->status !== StatusCertificado::ENTREGUE) {
            return response()->json([
                'message' => 'Não é possível editar um certificado que já foi avaliado.'
            ], 403);
        }

        $validated = $request->validate([
            'categoria_id' => ['sometimes', 'integer', 'exists:categorias,id'],
            'nome_certificado' => ['sometimes', 'string', 'max:255'],
            'instituicao' => ['sometimes', 'string', 'max:255'],
            'data_emissao' => ['sometimes', 'date'],
            'carga_horaria_solicitada' => ['sometimes', 'integer', 'min:1'],
            'arquivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $certificado->update($validated);

        if ($request->hasFile('arquivo')) {

            if ($certificado->arquivo_url) {
                Storage::disk('public')->delete($certificado->arquivo_url);
            }

            $path = $request->file('arquivo')->store('certificados', 'public');

            $certificado->update([
                'arquivo_url' => $path
            ]);
        }

        $certificado->load('categoria');

        return new CertificadoResource($certificado);
    }

    /**
     * DESTROY — Remove um certificado
     */
    public function destroy(Certificado $certificado)
    {
        if ($certificado->status !== StatusCertificado::ENTREGUE) {
            return response()->json([
                'message' => 'Não é possível excluir um certificado que já foi avaliado.'
            ], 403);
        }

        if ($certificado->arquivo_url) {
            Storage::disk('public')->delete($certificado->arquivo_url);
        }

        $certificado->delete();

        return response()->noContent();
    }

    /**
     * Exibe o arquivo PDF do certificado de forma segura
     */
    public function showArquivo(Certificado $certificado)
    {
        $user = Auth::user();

        // 1. Regras de Segurança (Autorização)
        // Se for aluno, só pode acessar se o certificado for dele
        if ($user->isAluno() && $certificado->aluno_id !== $user->id) {
            return response()->json(['message' => 'Acesso negado. Este certificado pertence a outro aluno.'], 403);
        }
        
        // Se for coordenador, só pode acessar se o aluno for do seu curso
        if ($user->isCoordenador()) {
            // Carrega o aluno para verificar o curso, se ainda não estiver carregado
            $certificado->loadMissing('aluno'); 
            
            if ($certificado->aluno->curso_id !== $user->curso_id) {
                 return response()->json(['message' => 'Acesso negado. Aluno de outro curso.'], 403);
            }
        }

        // 2. Verifica se o arquivo físico existe no disco
        if (!$certificado->arquivo_url || !Storage::disk('public')->exists($certificado->arquivo_url)) {
            return response()->json(['message' => 'Arquivo PDF não encontrado no servidor.'], 404);
        }

        // 3. Retorna o PDF para visualização no navegador
        // Nota: Se quiser forçar o download em vez de exibir, troque 'response' por 'download'
        return Storage::disk('public')->response($certificado->arquivo_url);
    }
}
```

## Arquivo: app\Http\Controllers\ConfiguracaoController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfiguracaoController extends Controller
{
    // [cite: 51]
    public function index()
    {
        // Gate 'is-admin' na rota
        // Retorna um objeto chave/valor
        return Configuracao::all()->pluck('valor', 'chave');
    }

    // [cite: 51]
    public function update(Request $request)
    {
        // Gate 'is-admin' na rota
        // Espera um objeto JSON { "chave": "valor" }
        $dados = $request->all();

        foreach ($dados as $chave => $valor) {
            Configuracao::updateOrCreate(
                ['chave' => $chave],
                ['valor' => $valor]
            );
        }

        return response()->json(['message' => 'Configurações atualizadas.']);
    }
}

```

## Arquivo: app\Http\Controllers\Controller.php
```php
<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
}

```

## Arquivo: app\Http\Controllers\CursoController.php
```php
<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Http\Resources\CursoResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    /**
     * Lista os cursos (Público/Autenticado para selects)
     */
    public function index()
    {
        $cursos = Curso::orderBy('nome')->get();
        return CursoResource::collection($cursos);
    }

    /**
     * Exibe um curso específico
     */
    public function show(Curso $curso)
    {
        return new CursoResource($curso);
    }

    /**
     * Cria um novo curso (Admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:cursos,nome'],
            'horas_necessarias' => ['required', 'integer', 'min:1'],
        ]);

        $curso = Curso::create($validated);

        return new CursoResource($curso);
    }

    /**
     * Atualiza um curso existente (Admin)
     */
    public function update(Request $request, Curso $curso)
    {
        $validated = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cursos', 'nome')->ignore($curso->id)
            ],
            'horas_necessarias' => ['required', 'integer', 'min:1'],
        ]);

        $curso->update($validated);

        return new CursoResource($curso);
    }

    /**
     * Remove um curso (Admin)
     */
    public function destroy(Curso $curso)
    {
        // Impede a exclusão se houver usuários vinculados a este curso
        if ($curso->users()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir este curso pois existem usuários vinculados a ele.'
            ], 422);
        }

        $curso->delete();

        return response()->noContent();
    }
}

```

## Arquivo: app\Http\Controllers\UsuarioController.php
```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusCertificado;
use App\Enums\TipoUsuario;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Resources\UserResource;
use App\Http\Resources\ProgressoResource;

class UsuarioController extends Controller
{
    /**
     * Retorna os dados do próprio usuário autenticado
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('curso');

        return new UserResource($user);
    }

    /**
     * Lista usuários
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $query = User::query()->with('curso');

        // Se for coordenador, restringe a busca aos ALUNOS do seu próprio CURSO
        if ($authUser->isCoordenador()) {
            $query->where('tipo', TipoUsuario::ALUNO->value)
                  ->where('curso_id', $authUser->curso_id);
        } else {
            // Filtro normal de tipo para Admin/Secretaria
            if ($request->has('tipo')) {
                $query->where('tipo', $request->tipo);
            }
        }

        return UserResource::collection($query->get());
    }

    /**
     * Cria usuário
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'cpf' => ['required', 'string', 'max:14', 'unique:users'],
            'data_nascimento' => ['required', 'date'],
            'matricula' => ['nullable', 'string', 'unique:users'],
            'password' => ['nullable', 'string', 'min:6'],
            'tipo' => ['required', Rule::enum(TipoUsuario::class)],
            'curso_id' => [
                'nullable',
                'exists:cursos,id',
                Rule::requiredIf($request->tipo === TipoUsuario::ALUNO->value),
            ],
            'fase' => [
                'nullable',
                'integer',
                Rule::requiredIf($request->tipo === TipoUsuario::ALUNO->value),
            ],
        ]);

        $user = User::create($data);

        return new UserResource($user);
    }

    /**
     * Exibe um usuário específico
     */
    public function show(User $user)
    {
        $user->load('curso');

        return new UserResource($user);
    }

    /**
     * Atualiza usuário
     */
    public function update(Request $request, User $user)
    {
        $authUser = $request->user();

        if (
            !$authUser->isAdmin() &&
            !$authUser->isSecretaria() &&
            $authUser->id !== $user->id
        ) {
            abort(403, 'Ação não autorizada.');
        }

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'cpf' => [
                'required',
                'string',
                'max:14',
                Rule::unique('users')->ignore($user->id),
            ],
            'matricula' => [
                'nullable',
                'string',
                Rule::unique('users')->ignore($user->id),
            ],
            'data_nascimento' => ['required', 'date'],
            'password' => ['nullable', 'string', 'min:6'],
            'tipo' => ['required', Rule::enum(TipoUsuario::class)],
            'curso_id' => ['nullable', 'exists:cursos,id'],
            'fase' => ['nullable', 'integer'],
        ]);

        $user->update($validated);

        return new UserResource($user);
    }

    /**
     * Remove usuário
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->noContent();
    }

    /**
     * Retorna progresso do aluno (com horas por categoria)
     */
    public function getProgresso(User $user)
    {
        $certificadosAprovados = $user->certificadosSubmetidos()
            ->with('categoria')
            ->whereIn('status', [
                StatusCertificado::APROVADO,
                StatusCertificado::APROVADO_COM_RESSALVAS,
            ])
            ->get();

        $totalAprovadas = $certificadosAprovados->sum('horas_validadas');

        $horasPorCategoria = $certificadosAprovados
            ->groupBy(function ($certificado) {
                return $certificado->categoria->nome ?? 'Sem Categoria';
            })
            ->map(function ($certificados) {
                return $certificados->sum('horas_validadas');
            });

        $horasNecessarias = $user->curso->horas_necessarias ?? 0;

        return new ProgressoResource([
            'total_horas_aprovadas' => (int) $totalAprovadas,
            'horas_necessarias' => (int) $horasNecessarias,
            'horas_por_categoria' => $horasPorCategoria,
        ]);
    }

    /**
     * Atualiza avatar do usuário logado
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();

        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_url' => $path,
        ]);

        return response()->json([
            'avatar_url' => Storage::url($path),
        ]);
    }

    /**
     * Exibe a foto de perfil (Avatar)
     */
    public function showAvatar($filename)
    {
        $path = 'avatars/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Avatar não encontrado.'], 404);
        }

        // Retorna o arquivo com os cabeçalhos corretos para exibir no navegador
        return Storage::disk('public')->response($path);
    }

    /**
     * Importa usuários em lote
     */
    public function import(Request $request)
    {
        $request->validate([
            'usuarios' => ['required', 'array'],
            'usuarios.*.nome' => ['required'],
            'usuarios.*.email' => ['required', 'email'],
            'usuarios.*.cpf' => ['required'],
            'usuarios.*.tipo' => ['required'],
        ]);

        $count = 0;

        foreach ($request->usuarios as $userData) {

            $password = $userData['password'] ?? '12345678';

            if (!str_starts_with($password, '$2y$')) {

                if (
                    isset($userData['data_nascimento']) &&
                    empty($userData['password'])
                ) {
                    $password = \Carbon\Carbon::parse(
                        $userData['data_nascimento']
                    )->format('dmY');
                }

                $password = Hash::make($password);
            }

            User::updateOrCreate(
                ['cpf' => $userData['cpf']],
                [
                    'nome' => $userData['nome'],
                    'email' => $userData['email'],
                    'data_nascimento' => $userData['data_nascimento'] ?? null,
                    'password' => $password,
                    'tipo' => $userData['tipo'],
                    'curso_id' => $userData['curso_id'] ?? null,
                    'fase' => $userData['fase'] ?? null,
                    'matricula' => $userData['matricula'] ?? null,
                ]
            );

            $count++;
        }

        return response()->json([
            'message' => "Importação concluída. {$count} usuários processados.",
        ]);
    }
}
```

## Arquivo: app\Http\Resources\AuthPayloadResource.php
```php
<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// [cite: 20] Formata a resposta do login
class AuthPayloadResource extends JsonResource
{
    public function __construct($user, $token)
    {
        parent::__construct($user);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->token,
            'usuario' => new UserResource($this->resource), // 'userData' no front-end [cite: 24]
        ];
    }
}

```

## Arquivo: app\Http\Resources\CategoriaResource.php
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
        ];
    }
}

```

## Arquivo: app\Http\Resources\CertificadoResource.php
```php
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
            'arquivo_url' => url('/api/certificados/' . $this->id . '/arquivo'),

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

```

## Arquivo: app\Http\Resources\CursoResource.php
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CursoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'horas_necessarias' => $this->horas_necessarias,
        ];
    }
}

```

## Arquivo: app\Http\Resources\ProgressoResource.php
```php
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
```

## Arquivo: app\Http\Resources\UserResource.php
```php
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
                ? url('/api/usuarios/' . $this->avatar_url) // Note: extraia apenas o nome do arquivo se necessário
                : null,

            'fase' => $this->fase,

            'curso' => new CursoResource($this->whenLoaded('curso')),

            // Linha adicionada para o frontend conseguir filtrar por data
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
```

## Arquivo: app\Models\Audit.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    protected $table = 'audits';

    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Relacionamento com o objeto que foi alterado (User ou Certificado)
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // Quem fez a alteração
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

```

## Arquivo: app\Models\Categoria.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = ['nome'];
}

```

## Arquivo: app\Models\Certificado.php
```php
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

```

## Arquivo: app\Models\Configuracao.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    use HasFactory;

    protected $table = 'configuracoes';
    protected $primaryKey = 'chave';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['chave', 'valor'];
}

```

## Arquivo: app\Models\Curso.php
```php
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

```

## Arquivo: app\Models\User.php
```php
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

```

## Arquivo: app\Observers\AuditObserver.php
```php
<?php

namespace App\Observers;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->recordAudit($model, 'created', null, $model->getAttributes());
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        // Em um Observer 'updated', o modelo já foi salvo.
        // getChanges() retorna o array do que mudou.
        $newValues = $model->getChanges();

        // Remove chaves de sistema que não interessam (ex: updated_at)
        unset($newValues['updated_at']);

        if (empty($newValues)) {
            return;
        }

        // Pega os valores originais correspondentes às chaves que mudaram
        $oldValues = array_intersect_key($model->getOriginal(), $newValues);

        $this->recordAudit($model, 'updated', $oldValues, $newValues);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->recordAudit($model, 'deleted', $model->getAttributes(), null);
    }

    /**
     * Método auxiliar para salvar no banco
     */
    protected function recordAudit(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        // Evita erro se rodar via seeder/tinker sem usuário logado
        $userId = Auth::id();

        Audit::create([
            'user_id'        => $userId,
            'event'          => $event,
            'auditable_type' => $model->getMorphClass(), // Ex: 'App\Models\User'
            'auditable_id'   => $model->getKey(),
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'url'            => Request::fullUrl(),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }
}

```

## Arquivo: app\Providers\AppServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Certificado;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Remove wrapping do JSON
        JsonResource::withoutWrapping();

        // Observers
        User::observe(AuditObserver::class);
        Certificado::observe(AuditObserver::class);

        // Gates de Perfil
        Gate::define('is-admin', fn (User $user) => $user->isAdmin());
        Gate::define('is-secretaria', fn (User $user) => $user->isSecretaria());
        Gate::define('is-coordenador', fn (User $user) => $user->isCoordenador());
        Gate::define('is-aluno', fn (User $user) => $user->isAluno());

        // Apenas Admin e Secretaria gerenciam usuários e veem o histórico global
        Gate::define('manage-users', fn (User $user) => $user->isAdmin() || $user->isSecretaria());
        Gate::define('view-all-history', fn (User $user) => $user->isAdmin() || $user->isSecretaria());

        // Novo Gate: Coordenador também pode visualizar (para listar na index)
        Gate::define('view-users', fn (User $user) => $user->isAdmin() || $user->isSecretaria() || $user->isCoordenador());

        // Gates de Nível de Recurso
        Gate::define('avaliar-certificado', function (User $coordenador, $certificado) {
            if (!$coordenador->isCoordenador()) {
                return false;
            }

            return $coordenador->curso_id === $certificado->aluno->curso_id;
        });

        Gate::define('view-progresso', function (User $user, $aluno) {
            if ($user->isAdmin() || $user->isSecretaria()) {
                return true;
            }

            if ($user->isCoordenador() && $user->curso_id === $aluno->curso_id) {
                return true;
            }

            return $user->id === $aluno->id;
        });
    }
}

```

## Arquivo: app\Providers\AuthServiceProvider.php
```php
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

```

## Arquivo: config\app.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];

```

## Arquivo: config\auth.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

```

## Arquivo: config\cache.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

];

```

## Arquivo: config\cors.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Defina os caminhos onde o CORS será aplicado.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Métodos HTTP permitidos.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origens permitidas (Live Server).
    |
    */

    'allowed_origins' => [
        'https://leods4.github.io',
        'https://panoramic-figure-mushroom.ngrok-free.dev',
        'https://mariaeduarda1306.github.io',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers permitidos pelo navegador. '*' +
    | explicitamente Authorization para evitar falha no preflight.
    |
    */

    'allowed_headers' => ['*', 'Authorization', 'ngrok-skip-browser-warning'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers que podem ser lidos pelo frontend.
    | Authorization aparece aqui porque alguns fluxos enviam tokens.
    |
    */

    'exposed_headers' => ['Authorization'],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    */

    'max_age' => 86400, // 24h de cache do preflight

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | OBRIGATÓRIO quando usa Authorization: Bearer <token>
    | Mesmo sem cookies, o browser considera como "credenciais".
    |
    */

    'supports_credentials' => true,

];

```

## Arquivo: config\database.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];

```

## Arquivo: config\filesystems.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

```

## Arquivo: config\logging.php
```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];

```

## Arquivo: config\mail.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];

```

## Arquivo: config\queue.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];

```

## Arquivo: config\sanctum.php
```php
<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];

```

## Arquivo: config\services.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

```

## Arquivo: config\session.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default session driver that is utilized for
    | incoming requests. Laravel supports a variety of storage options to
    | persist session data. Database storage is a great default choice.
    |
    | Supported: "file", "cookie", "database", "memcached",
    |            "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to expire immediately when the browser is closed then you may
    | indicate that via the expire_on_close configuration option.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily specify that all of your session data
    | should be encrypted before it's stored. All encryption is performed
    | automatically by Laravel and you may use the session like normal.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using one of the framework's cache driven session backends, you may
    | define the cache store which should be used to store the session data
    | between requests. This must match one of your defined cache stores.
    |
    | Affects: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | The session cookie path determines the path for which the cookie will
    | be regarded as available. Typically, this will be the root path of
    | your application, but you're free to change this when necessary.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | This value determines the domain and subdomains the session cookie is
    | available to. By default, the cookie will be available to the root
    | domain and all subdomains. Typically, this shouldn't be changed.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | By setting this option to true, session cookies will only be sent back
    | to the server if the browser has a HTTPS connection. This will keep
    | the cookie from being sent to you when it can't be done securely.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will prevent JavaScript from accessing the
    | value of the cookie and the cookie will only be accessible through
    | the HTTP protocol. It's unlikely you should disable this option.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | This option determines how your cookies behave when cross-site requests
    | take place, and can be used to mitigate CSRF attacks. By default, we
    | will set this value to "lax" to permit secure cross-site requests.
    |
    | See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will tie the cookie to the top-level site for
    | a cross-site context. Partitioned cookies are accepted by the browser
    | when flagged "secure" and the Same-Site attribute is set to "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];

```

## Arquivo: database\migrations\0001_01_01_000001_create_cache_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};

```

## Arquivo: database\migrations\0001_01_01_000002_create_jobs_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};

```

## Arquivo: database\migrations\0001_01_01_103141_create_cursos_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->unsignedInteger('horas_necessarias');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};

```

## Arquivo: database\migrations\0001_01_01_103404_create_configuracoes_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela simples de Chave/Valor
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave')->primary();
            $table->string('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};

```

## Arquivo: database\migrations\0002_01_01_000000_create_users_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('cpf')->unique();

            // Campo vindo da segunda migration
            $table->date('data_nascimento')->nullable()->after('cpf');

            $table->string('matricula')->nullable()->unique();
            $table->string('password');
            $table->string('tipo'); // ALUNO, COORDENADOR, SECRETARIA, ADMINISTRADOR
            $table->string('avatar_url')->nullable();

            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->unsignedInteger('fase')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

```

## Arquivo: database\migrations\2025_11_18_103823_create_personal_access_tokens_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};

```

## Arquivo: database\migrations\2025_11_22_152711_create_categorias_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique(); // Garante nomes únicos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};

```

## Arquivo: database\migrations\2025_11_23_103308_create_certificados_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aluno_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // atualizado conforme o arquivo mudancas.txt
            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->string('nome_certificado');
            $table->string('instituicao');
            $table->date('data_emissao');
            $table->unsignedInteger('carga_horaria_solicitada');
            $table->string('arquivo_url'); // caminho no storage

            $table->string('status')->default('ENTREGUE');
            // ENTREGUE, APROVADO, REPROVADO, APROVADO_COM_RESSALVAS

            $table->foreignId('coordenador_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('horas_validadas')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamp('data_validacao')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};

```

## Arquivo: database\migrations\2025_11_25_102440_create_audits_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            // Quem realizou a ação (pode ser nulo se for uma tarefa do sistema)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // O tipo de evento: 'created', 'updated', 'deleted', 'restored'
            $table->string('event');

            // Polimorfismo: armazena 'App\Models\User' e o ID 1, por exemplo
            $table->morphs('auditable');

            // Valores antes e depois da alteração (formato JSON)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Informações extras de rastreamento
            $table->string('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable(); // Ex: "aprovacao", "correcao_dados"

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};

```

## Arquivo: database\seeders\DatabaseSeeder.php
```php
<?php

namespace Database\Seeders;

use App\Enums\StatusCertificado;
use App\Enums\TipoUsuario;
use App\Models\Categoria;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Envolve tudo em uma transação para garantir integridade
        DB::transaction(function () {
            $this->command->info('Iniciando o seeding do banco de dados...');

            $cursos = $this->seedCursos();
            $categorias = $this->seedCategorias();
            $usuariosBase = $this->seedUsuariosBase($cursos['ads']);
            
            // Gera dados em massa para testes de carga e paginação
            $this->seedAlunosECertificadosFaker($cursos['ads'], $categorias, $usuariosBase['coordenador']);

            $this->command->info('Seeding concluído com sucesso!');
        });
    }

    private function seedCursos(): array
    {
        $this->command->info('Semeando Cursos...');

        $cursoAds = Curso::firstOrCreate(
            ['nome' => 'Análise e Desenvolvimento de Sistemas'],
            ['horas_necessarias' => 262]
        );

        $outrosCursos = [
            'Pedagogia' => 150,
            'Administração' => 150,
            'Processos Gerenciais' => 150
        ];

        foreach ($outrosCursos as $nome => $horas) {
            Curso::firstOrCreate(['nome' => $nome], ['horas_necessarias' => $horas]);
        }

        return ['ads' => $cursoAds];
    }

    private function seedCategorias(): \Illuminate\Database\Eloquent\Collection
    {
        $this->command->info('Semeando Categorias...');

        $nomesCategorias = [
            'Científico/Acadêmicas',
            'Sócio-Culturais',
            'Prática Profissional'
        ];

        foreach ($nomesCategorias as $nome) {
            Categoria::firstOrCreate(['nome' => $nome]);
        }

        return Categoria::all();
    }

    private function seedUsuariosBase(Curso $cursoAds): array
    {
        $this->command->info('Semeando Usuários Base...');

        // 1. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@fmp.edu.br'],
            [
                'nome'            => 'Administrador Principal',
                'cpf'             => '000.000.000-00',
                'data_nascimento' => '1990-01-01',
                'password'        => Hash::make('admin123'),
                'tipo'            => TipoUsuario::ADMINISTRADOR,
            ]
        );

        // 2. Secretaria
        $secretaria = User::firstOrCreate(
            ['email' => 'secretaria@fmp.edu.br'],
            [
                'nome'            => 'Responsável Secretaria',
                'cpf'             => '111.111.111-11',
                'data_nascimento' => '1992-05-10',
                'password'        => Hash::make('sec123'),
                'tipo'            => TipoUsuario::SECRETARIA,
            ]
        );

        // 3. Coordenador
        $coordenador = User::firstOrCreate(
            ['email' => 'coord.ads@fmp.edu.br'],
            [
                'nome'            => 'Prof. Coordenador ADS',
                'cpf'             => '222.222.222-22',
                'data_nascimento' => '1985-08-20',
                'password'        => Hash::make('coord123'),
                'tipo'            => TipoUsuario::COORDENADOR,
                'curso_id'        => $cursoAds->id,
            ]
        );

        // 4. Aluno Específico para testes rápidos
        $alunoJoao = User::firstOrCreate(
            ['email' => 'aluno@fmp.edu.br'],
            [
                'nome'            => 'João da Silva',
                'cpf'             => '333.333.333-33',
                'data_nascimento' => '2003-04-15',
                'matricula'       => '20250001',
                'password'        => Hash::make('aluno123'),
                'tipo'            => TipoUsuario::ALUNO,
                'avatar_url'      => 'https://ui-avatars.com/api/?name=Joao+Silva',
                'curso_id'        => $cursoAds->id,
                'fase'            => 3,
            ]
        );

        return [
            'admin' => $admin,
            'secretaria' => $secretaria,
            'coordenador' => $coordenador,
            'aluno_joao' => $alunoJoao
        ];
    }

    private function seedAlunosECertificadosFaker(Curso $curso, $categorias, User $coordenador): void
    {
        $this->command->info('Gerando Alunos e Certificados dinâmicos com Faker...');
        $faker = Faker::create('pt_BR');

        // Cria 10 alunos aleatórios
        for ($i = 0; $i < 10; $i++) {
            $aluno = User::firstOrCreate(
                ['email' => $faker->unique()->safeEmail()],
                [
                    'nome'            => $faker->name(),
                    'cpf'             => $faker->unique()->cpf(false), // cpf sem formatação, o Mutator cuida disso
                    'data_nascimento' => $faker->date('Y-m-d', '2005-01-01'),
                    'matricula'       => $faker->unique()->numerify('2025####'),
                    'password'        => Hash::make('aluno123'),
                    'tipo'            => TipoUsuario::ALUNO,
                    'curso_id'        => $curso->id,
                    'fase'            => $faker->numberBetween(1, 6),
                ]
            );

            // Para cada aluno, gera entre 1 e 4 certificados
            $numCertificados = $faker->numberBetween(1, 4);
            for ($j = 0; $j < $numCertificados; $j++) {
                $status = $faker->randomElement([
                    StatusCertificado::ENTREGUE,
                    StatusCertificado::APROVADO,
                    StatusCertificado::REPROVADO,
                    StatusCertificado::APROVADO_COM_RESSALVAS
                ]);

                $cargaSolicitada = $faker->numberBetween(5, 50);
                $isAvaliado = $status !== StatusCertificado::ENTREGUE;

                Certificado::create([
                    'aluno_id'                 => $aluno->id,
                    'categoria_id'             => $categorias->random()->id,
                    'nome_certificado'         => 'Curso de ' . $faker->words(3, true),
                    'instituicao'              => $faker->company(),
                    'data_emissao'             => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                    'carga_horaria_solicitada' => $cargaSolicitada,
                    'arquivo_url'              => 'certificados/dummy.pdf', // Assumindo que este arquivo exista no storage
                    'status'                   => $status,
                    
                    // Preenche dados de avaliação apenas se não estiver "ENTREGUE"
                    'coordenador_id'           => $isAvaliado ? $coordenador->id : null,
                    'data_validacao'           => $isAvaliado ? Carbon::now()->subDays($faker->numberBetween(1, 30)) : null,
                    'observacao'               => $isAvaliado ? $faker->sentence() : null,
                    'horas_validadas'          => $status === StatusCertificado::APROVADO ? $cargaSolicitada : 
                                                 ($status === StatusCertificado::REPROVADO ? 0 : 
                                                 ($isAvaliado ? $faker->numberBetween(1, $cargaSolicitada) : null)),
                ]);
            }
        }
    }
}
```

## Arquivo: routes\api.php
```php
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
        Route::get('/', [CertificadoController::class, 'show']); // Adicionar policy (aluno dono, coord, sec, admin)
        
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
```

## Arquivo: routes\console.php
```php
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

```


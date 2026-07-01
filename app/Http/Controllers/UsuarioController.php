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
     * Lista usuários com suporte a filtros
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $query = User::query()->with('curso');

        // --------------------------------------------------------
        // 1. FILTROS AVANÇADOS
        // --------------------------------------------------------

        // Busca geral por Nome, CPF ou Matrícula
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('nome', 'like', "%{$term}%")
                  ->orWhere('cpf', 'like', "%{$term}%")
                  ->orWhere('matricula', 'like', "%{$term}%");
            });
        }

        // Filtro específico por Fase (Útil para alunos)
        if ($request->filled('fase')) {
            $query->where('fase', $request->fase);
        }

        // --------------------------------------------------------
        // 2. REGRAS DE VISUALIZAÇÃO POR PERFIL
        // --------------------------------------------------------

        if ($authUser->isCoordenador()) {
            // Se for coordenador, restringe obrigatoriamente aos ALUNOS do seu próprio CURSO
            $query->where('tipo', TipoUsuario::ALUNO->value)
                  ->where('curso_id', $authUser->curso_id);
        } else {
            // Se for Admin ou Secretaria, eles podem filtrar por curso e por tipo de usuário livremente
            if ($request->filled('curso_id')) {
                $query->where('curso_id', $request->curso_id);
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }
        }

        // --------------------------------------------------------
        // 3. ORDENAÇÃO E RETORNO
        // --------------------------------------------------------
        
        // Ordena os usuários por nome em ordem alfabética para facilitar a leitura no Front-end
        $query->orderBy('nome');

        // DICA: Se a sua base de dados crescer muito, substitua $query->get() por $query->paginate(15)
        return UserResource::collection($query->get());
    }

    /**
     * Cria usuário
     */
    public function store(Request $request)
    {
        // 1. Formata o CPF antes da validação
        if ($request->filled('cpf')) {
            $cpfLimpo = preg_replace('/\D/', '', $request->cpf);
            
            if (strlen($cpfLimpo) === 11) {
                $request->merge([
                    'cpf' => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo)
                ]);
            }
        }

        // 2. Agora o Validator vai testar a string já formatada (ex: 000.000.000-00)
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'cpf' => ['required', 'string', 'max:14', 'unique:users'], // Passará corretamente
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

        // Verifica se o usuário tem permissão para editar (Admin, Secretaria ou o próprio dono do perfil)
        if (
            !$authUser->isAdmin() &&
            !$authUser->isSecretaria() &&
            $authUser->id !== $user->id
        ) {
            abort(403, 'Ação não autorizada.');
        }

        // 1. Formata o CPF antes de montar as regras de validação
        if ($request->filled('cpf')) {
            $cpfLimpo = preg_replace('/\D/', '', $request->cpf);
            
            if (strlen($cpfLimpo) === 11) {
                $request->merge([
                    'cpf' => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo)
                ]);
            }
        }

        // 2. Regras Base (O que qualquer usuário pode alterar no próprio perfil)
        $rules = [
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'data_nascimento' => ['required', 'date'],
            'password' => ['nullable', 'string', 'min:6'],
        ];

        // 3. Regras Restritas (O que apenas Admin e Secretaria podem alterar)
        if ($authUser->isAdmin() || $authUser->isSecretaria()) {
            $rules['cpf'] = [
                'required',
                'string',
                'max:14',
                Rule::unique('users')->ignore($user->id),
            ];
            $rules['matricula'] = [
                'nullable',
                'string',
                Rule::unique('users')->ignore($user->id),
            ];
            $rules['tipo'] = ['required', Rule::enum(TipoUsuario::class)];
            $rules['curso_id'] = ['nullable', 'exists:cursos,id'];
            $rules['fase'] = ['nullable', 'integer'];
        }

        $validated = $request->validate($rules);

        // Não atualiza a senha caso ela não tenha sido informada
        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            // Se informou a senha, ela precisa ser hasheada antes de salvar
            $validated['password'] = Hash::make($validated['password']);
        }

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
     * Remove o avatar do utilizador autenticado
     */
    public function destroyAvatar(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        // Verifica se o utilizador tem um avatar associado
        if ($user->avatar_url) {
            // Apaga o ficheiro do armazenamento público
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_url);
            
            // Atualiza a base de dados para remover a referência do ficheiro
            $user->update([
                'avatar_url' => null,
            ]);

            return response()->json(['message' => 'Avatar removido com sucesso.']);
        }

        return response()->json(['message' => 'O utilizador não possui um avatar para remover.'], 404);
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

        // Alterado para retornar diretamente um JSON
        return response()->json([
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
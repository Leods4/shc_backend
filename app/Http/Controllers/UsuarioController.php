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
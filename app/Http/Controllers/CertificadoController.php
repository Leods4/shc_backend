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
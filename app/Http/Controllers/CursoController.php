<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    /**
     * Formata o objeto Curso para array (substitui o CursoResource)
     */
    private function formatCurso(Curso $curso): array
    {
        return [
            'id' => $curso->id,
            'nome' => $curso->nome,
            'horas_necessarias' => $curso->horas_necessarias,
        ];
    }

    /**
     * Lista os cursos (Público/Autenticado para selects)
     */
    public function index()
    {
        $cursos = Curso::orderBy('nome')->get();
        
        return response()->json(
            $cursos->map(fn($curso) => $this->formatCurso($curso))
        );
    }

    /**
     * Exibe um curso específico
     */
    public function show(Curso $curso)
    {
        return response()->json($this->formatCurso($curso));
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

        return response()->json($this->formatCurso($curso), 201);
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

        return response()->json($this->formatCurso($curso));
    }

    /**
     * Remove um curso (Admin)
     */
    public function destroy(Curso $curso)
    {
        // O bloqueio de if ($curso->users()->exists()) foi removido.
        // O banco de dados se encarregará de setar 'curso_id' como NULL 
        // nos usuários, graças ao nullOnDelete() da migration.

        $curso->delete();

        return response()->noContent();
    }
}
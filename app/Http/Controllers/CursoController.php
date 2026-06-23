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

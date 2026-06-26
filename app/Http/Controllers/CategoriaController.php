<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Formata o objeto Categoria para array (substitui o CategoriaResource)
     */
    private function formatCategoria(Categoria $categoria): array
    {
        return [
            'id' => $categoria->id,
            'nome' => $categoria->nome,
        ];
    }

    /**
     * Lista todas as categorias (Usado no dropdown de cadastro de horas)
     */
    public function index()
    {
        // Retorna ordenado alfabeticamente
        $categorias = Categoria::orderBy('nome')->get();
        
        return response()->json(
            $categorias->map(fn($categoria) => $this->formatCategoria($categoria))
        );
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

        return response()->json($this->formatCategoria($categoria), 201);
    }

    /**
     * Exibe uma categoria específica
     */
    public function show(Categoria $categoria)
    {
        return response()->json($this->formatCategoria($categoria));
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

        return response()->json($this->formatCategoria($categoria));
    }

    /**
     * Remove uma categoria (Apenas Admin)
     */
    public function destroy(Categoria $categoria)
    {
        if (\App\Models\Certificado::where('categoria_id', $categoria->id)->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir esta categoria, pois existem certificados vinculados a ela.'
            ], 422);
        }

        $categoria->delete();

        return response()->noContent();
    }
}
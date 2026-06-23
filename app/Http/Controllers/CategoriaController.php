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
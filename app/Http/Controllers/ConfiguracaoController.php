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

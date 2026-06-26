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
        $request->validate([
            'cpf' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $cpfLimpo = preg_replace('/\D/', '', $request->cpf);

        $cpfFormatado = strlen($cpfLimpo) === 11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfLimpo)
            : $request->cpf;

        $user = User::where('cpf', $cpfFormatado)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'cpf' => ['CPF ou senha inválidos.'],
            ]);
        }

        // REMOVIDO: $user->tokens()->delete();

        // O Sanctum agora criará um token paralelo a qualquer outro existente
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
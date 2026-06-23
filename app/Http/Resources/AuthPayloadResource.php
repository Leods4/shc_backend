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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            // Quem realizou a ação (pode ser nulo se for uma tarefa do sistema)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // O tipo de evento: 'created', 'updated', 'deleted', 'restored'
            $table->string('event');

            // Polimorfismo: armazena 'App\Models\User' e o ID 1, por exemplo
            $table->morphs('auditable');

            // Valores antes e depois da alteração (formato JSON)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Informações extras de rastreamento
            $table->string('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable(); // Ex: "aprovacao", "correcao_dados"

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};

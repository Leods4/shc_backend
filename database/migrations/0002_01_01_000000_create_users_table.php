<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('cpf')->unique();

            // Campo vindo da segunda migration
            $table->date('data_nascimento')->nullable()->after('cpf');

            $table->string('matricula')->nullable()->unique();
            $table->string('password');
            $table->string('tipo'); // ALUNO, COORDENADOR, SECRETARIA, ADMINISTRADOR
            $table->string('avatar_url')->nullable();

            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->unsignedInteger('fase')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

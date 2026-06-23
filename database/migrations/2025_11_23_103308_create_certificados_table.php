<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aluno_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // atualizado conforme o arquivo mudancas.txt
            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->string('nome_certificado');
            $table->string('instituicao');
            $table->date('data_emissao');
            $table->unsignedInteger('carga_horaria_solicitada');
            $table->string('arquivo_url'); // caminho no storage

            $table->string('status')->default('ENTREGUE');
            // ENTREGUE, APROVADO, REPROVADO, APROVADO_COM_RESSALVAS

            $table->foreignId('coordenador_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('horas_validadas')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamp('data_validacao')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};

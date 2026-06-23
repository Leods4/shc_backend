<?php

namespace Database\Seeders;

use App\Enums\StatusCertificado;
use App\Enums\TipoUsuario;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\User;
use App\Models\Certificado;
use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Criar Cursos
        $cursoAds = Curso::create([
            'nome' => 'Análise e Desenvolvimento de Sistemas',
            'horas_necessarias' => 262
        ]);

        $outrosCursos = [
            'Pedagogia',
            'Administração',
            'Processos Gerenciais'
        ];

        foreach ($outrosCursos as $nomeCurso) {
            Curso::create([
                'nome' => $nomeCurso,
                'horas_necessarias' => 150
            ]);
        }

        // 2. Admin
        User::create([
            'nome'            => 'Administrador Principal',
            'email'           => 'admin@fmp.edu.br',
            'cpf'             => '000.000.000-00',
            'data_nascimento' => '1990-01-01',
            'password'        => Hash::make('admin123'),
            'tipo'            => TipoUsuario::ADMINISTRADOR,
        ]);

        // 3. Secretaria
        User::create([
            'nome'            => 'Responsável Secretaria',
            'email'           => 'secretaria@fmp.edu.br',
            'cpf'             => '111.111.111-11',
            'data_nascimento' => '1992-05-10',
            'password'        => Hash::make('sec123'),
            'tipo'            => TipoUsuario::SECRETARIA,
        ]);

        // 4. Coordenador (Vinculado a ADS)
        $coordAds = User::create([
            'nome'            => 'Prof. Coordenador ADS',
            'email'           => 'coord.ads@fmp.edu.br',
            'cpf'             => '222.222.222-22',
            'data_nascimento' => '1985-08-20',
            'password'        => Hash::make('coord123'),
            'tipo'            => TipoUsuario::COORDENADOR,
            'curso_id'        => $cursoAds->id,
        ]);

        // 5. Aluno João (Com certificados)
        $alunoJoao = User::create([
            'nome'            => 'João da Silva',
            'email'           => 'aluno@fmp.edu.br',
            'cpf'             => '333.333.333-33',
            'data_nascimento' => '2003-04-15',
            'matricula'       => '20250001',
            'password'        => Hash::make('aluno123'),
            'tipo'            => TipoUsuario::ALUNO,
            'avatar_url'      => 'https://ui-avatars.com/api/?name=Joao+Silva',
            'curso_id'        => $cursoAds->id,
            'fase'            => 3,
        ]);

        // 5b. Aluno Maria (Normalizada com senha padrão)
        User::create([
            'nome'            => 'Maria Oliveira',
            'email'           => 'aluno2@fmp.edu.br',
            'cpf'             => '444.444.444-44',
            'data_nascimento' => '2004-02-13',
            'matricula'       => '20250002',
            'password'        => Hash::make('aluno123'),
            'tipo'            => TipoUsuario::ALUNO,
            'curso_id'        => $cursoAds->id,
            'fase'            => 1,
        ]);

        // 5c. Novo Aluno Adicionado
        User::create([
            'nome'            => 'Carlos Souza',
            'email'           => 'aluno3@fmp.edu.br',
            'cpf'             => '555.555.555-55',
            'data_nascimento' => '2002-11-20',
            'matricula'       => '20250003',
            'password'        => Hash::make('aluno123'),
            'tipo'            => TipoUsuario::ALUNO,
            'curso_id'        => $cursoAds->id,
            'fase'            => 5,
        ]);

        // 6. Configurações inicias

        // 7. Categorias
        $nomesCategorias = [
            'Científico/Acadêmicas',
            'Sócio-Culturais',
            'Prática Profissional'
        ];

        $cats = [];
        foreach ($nomesCategorias as $nome) {
            $cats[$nome] = Categoria::create(['nome' => $nome]);
        }

        // 8. Certificados para João da Silva

        // Certificado 1: Entregue (Pendente de avaliação)
        Certificado::create([
            'aluno_id'                 => $alunoJoao->id,
            'categoria_id'             => $cats['Científico/Acadêmicas']->id,
            'nome_certificado'         => 'Curso de Laravel Avançado',
            'instituicao'              => 'Udemy',
            'data_emissao'             => '2024-10-10',
            'carga_horaria_solicitada' => 10,
            'arquivo_url'              => 'certificados/dummy.pdf',
            'status'                   => StatusCertificado::ENTREGUE, // Alterado de PENDENTE para ENTREGUE
        ]);

        // Certificado 2: Aprovado
        Certificado::create([
            'aluno_id'                 => $alunoJoao->id,
            'categoria_id'             => $cats['Prática Profissional']->id,
            'nome_certificado'         => 'Estágio Extracurricular',
            'instituicao'              => 'Empresa Tech Ltda',
            'data_emissao'             => '2024-05-20',
            'carga_horaria_solicitada' => 100,
            'horas_validadas'          => 100,
            'arquivo_url'              => 'certificados/estagio.pdf',
            'status'                   => StatusCertificado::APROVADO,
            'coordenador_id'           => $coordAds->id,
            'data_validacao'           => Carbon::now(),
            'observacao'               => 'Documentação correta.',
        ]);

        // Certificado 3: Reprovado
        Certificado::create([
            'aluno_id'                 => $alunoJoao->id,
            'categoria_id'             => $cats['Sócio-Culturais']->id,
            'nome_certificado'         => 'Workshop de Culinária',
            'instituicao'              => 'Escola Gastronômica',
            'data_emissao'             => '2024-01-15',
            'carga_horaria_solicitada' => 5,
            'arquivo_url'              => 'certificados/culinaria.pdf',
            'status'                   => StatusCertificado::REPROVADO, // Alterado de REJEITADO para REPROVADO
            'coordenador_id'           => $coordAds->id,
            'data_validacao'           => Carbon::now(),
            'observacao'               => 'Atividade não condiz com o projeto pedagógico do curso.',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\StatusCertificado;
use App\Enums\TipoUsuario;
use App\Models\Categoria;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Envolve tudo em uma transação para garantir integridade
        DB::transaction(function () {
            $this->command->info('Iniciando o seeding do banco de dados...');

            $cursos = $this->seedCursos();
            $categorias = $this->seedCategorias();
            $usuariosBase = $this->seedUsuariosBase($cursos['ads']);
            
            // Gera dados em massa para testes de carga e paginação
            $this->seedAlunosECertificadosFaker($cursos['ads'], $categorias, $usuariosBase['coordenador']);

            $this->command->info('Seeding concluído com sucesso!');
        });
    }

    private function seedCursos(): array
    {
        $this->command->info('Semeando Cursos...');

        $cursoAds = Curso::firstOrCreate(
            ['nome' => 'Análise e Desenvolvimento de Sistemas'],
            ['horas_necessarias' => 262]
        );

        $outrosCursos = [
            'Pedagogia' => 150,
            'Administração' => 150,
            'Processos Gerenciais' => 150
        ];

        foreach ($outrosCursos as $nome => $horas) {
            Curso::firstOrCreate(['nome' => $nome], ['horas_necessarias' => $horas]);
        }

        return ['ads' => $cursoAds];
    }

    private function seedCategorias(): \Illuminate\Database\Eloquent\Collection
    {
        $this->command->info('Semeando Categorias...');

        $nomesCategorias = [
            'Científico/Acadêmicas',
            'Sócio-Culturais',
            'Prática Profissional'
        ];

        foreach ($nomesCategorias as $nome) {
            Categoria::firstOrCreate(['nome' => $nome]);
        }

        return Categoria::all();
    }

    private function seedUsuariosBase(Curso $cursoAds): array
    {
        $this->command->info('Semeando Usuários Base...');

        // 1. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@fmp.edu.br'],
            [
                'nome'            => 'Administrador Principal',
                'cpf'             => '000.000.000-00',
                'data_nascimento' => '1990-01-01',
                'password'        => Hash::make('admin123'),
                'tipo'            => TipoUsuario::ADMINISTRADOR,
            ]
        );

        // 2. Secretaria
        $secretaria = User::firstOrCreate(
            ['email' => 'secretaria@fmp.edu.br'],
            [
                'nome'            => 'Responsável Secretaria',
                'cpf'             => '111.111.111-11',
                'data_nascimento' => '1992-05-10',
                'password'        => Hash::make('sec123'),
                'tipo'            => TipoUsuario::SECRETARIA,
            ]
        );

        // 3. Coordenador
        $coordenador = User::firstOrCreate(
            ['email' => 'coord.ads@fmp.edu.br'],
            [
                'nome'            => 'Prof. Coordenador ADS',
                'cpf'             => '222.222.222-22',
                'data_nascimento' => '1985-08-20',
                'password'        => Hash::make('coord123'),
                'tipo'            => TipoUsuario::COORDENADOR,
                'curso_id'        => $cursoAds->id,
            ]
        );

        // 4. Aluno Específico para testes rápidos
        $alunoJoao = User::firstOrCreate(
            ['email' => 'aluno@fmp.edu.br'],
            [
                'nome'            => 'João da Silva',
                'cpf'             => '333.333.333-33',
                'data_nascimento' => '2003-04-15',
                'matricula'       => '20250001',
                'password'        => Hash::make('aluno123'),
                'tipo'            => TipoUsuario::ALUNO,
                'avatar_url'      => 'https://ui-avatars.com/api/?name=Joao+Silva',
                'curso_id'        => $cursoAds->id,
                'fase'            => 3,
            ]
        );

        return [
            'admin' => $admin,
            'secretaria' => $secretaria,
            'coordenador' => $coordenador,
            'aluno_joao' => $alunoJoao
        ];
    }

    private function seedAlunosECertificadosFaker(Curso $curso, $categorias, User $coordenador): void
    {
        $this->command->info('Gerando Alunos e Certificados dinâmicos com Faker...');
        $faker = Faker::create('pt_BR');

        // Cria 10 alunos aleatórios
        for ($i = 0; $i < 10; $i++) {
            $aluno = User::firstOrCreate(
                ['email' => $faker->unique()->safeEmail()],
                [
                    'nome'            => $faker->name(),
                    'cpf'             => $faker->unique()->cpf(false), // cpf sem formatação, o Mutator cuida disso
                    'data_nascimento' => $faker->date('Y-m-d', '2005-01-01'),
                    'matricula'       => $faker->unique()->numerify('2025####'),
                    'password'        => Hash::make('aluno123'),
                    'tipo'            => TipoUsuario::ALUNO,
                    'curso_id'        => $curso->id,
                    'fase'            => $faker->numberBetween(1, 6),
                ]
            );

            // Para cada aluno, gera entre 1 e 4 certificados
            $numCertificados = $faker->numberBetween(1, 4);
            for ($j = 0; $j < $numCertificados; $j++) {
                $status = $faker->randomElement([
                    StatusCertificado::ENTREGUE,
                    StatusCertificado::APROVADO,
                    StatusCertificado::REPROVADO,
                    StatusCertificado::APROVADO_COM_RESSALVAS
                ]);

                $cargaSolicitada = $faker->numberBetween(5, 50);
                $isAvaliado = $status !== StatusCertificado::ENTREGUE;

                Certificado::create([
                    'aluno_id'                 => $aluno->id,
                    'categoria_id'             => $categorias->random()->id,
                    'nome_certificado'         => 'Curso de ' . $faker->words(3, true),
                    'instituicao'              => $faker->company(),
                    'data_emissao'             => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                    'carga_horaria_solicitada' => $cargaSolicitada,
                    'arquivo_url'              => 'certificados/dummy.pdf', // Assumindo que este arquivo exista no storage
                    'status'                   => $status,
                    
                    // Preenche dados de avaliação apenas se não estiver "ENTREGUE"
                    'coordenador_id'           => $isAvaliado ? $coordenador->id : null,
                    'data_validacao'           => $isAvaliado ? Carbon::now()->subDays($faker->numberBetween(1, 30)) : null,
                    'observacao'               => $isAvaliado ? $faker->sentence() : null,
                    'horas_validadas'          => $status === StatusCertificado::APROVADO ? $cargaSolicitada : 
                                                 ($status === StatusCertificado::REPROVADO ? 0 : 
                                                 ($isAvaliado ? $faker->numberBetween(1, $cargaSolicitada) : null)),
                ]);
            }
        }
    }
}
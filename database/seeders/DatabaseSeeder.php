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

            $cursoAds = $this->seedCursos();
            $categorias = $this->seedCategorias();
            $usuariosBase = $this->seedUsuariosBase($cursoAds);
            
            // Pega todos os cursos criados no banco para distribuir os alunos
            $todosOsCursos = Curso::all();

            // Gera dados em massa espalhando entre todos os cursos
            $this->seedAlunosECertificadosFaker($todosOsCursos, $categorias, $usuariosBase['coordenador']);

            $this->command->info('Seeding concluído com sucesso!');
        });
    }

    private function seedCursos(): Curso
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

        // Retornamos apenas o ADS para usar como base para o coordenador padrao
        return $cursoAds;
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
                'password'        => Hash::make('admin321'),
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

        // 2.1 Secretaria
        $secretaria = User::firstOrCreate(
            ['email' => 'elisangela.da.silva@fmp.edu.br'],
            [
                'nome'            => 'Elisangela da Silva',
                'cpf'             => '097.652.749-98',
                'data_nascimento' => '1997-05-11',
                'password'        => Hash::make('sec123'),
                'tipo'            => TipoUsuario::SECRETARIA,
            ]
        );
        
        // 3. Coordenador
        $coordenador = User::firstOrCreate(
            ['email' => 'guilherme.gomes@fmp.edu.br'],
            [
                'nome'            => 'Guilherme Gomes',
                'cpf'             => '125.621.669-06',
                'data_nascimento' => '1989-08-20',
                'password'        => Hash::make('coord123'),
                'tipo'            => TipoUsuario::COORDENADOR,
                'curso_id'        => $cursoAds->id,
            ]
        );

        // 3.1 Coordenador
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
                'nome'            => 'Armando da Silvera',
                'cpf'             => '333.333.333-33',
                'data_nascimento' => '2003-04-15',
                'matricula'       => '20250341',
                'password'        => Hash::make('aluno123'),
                'tipo'            => TipoUsuario::ALUNO,
                'avatar_url'      => 'https://ui-avatars.com/api/?name=Joao+Silva',
                'curso_id'        => $cursoAds->id,
                'fase'            => 3,
            ]
        );

        // 4. Aluno Específico para testes rápidos
        $alunoJoao = User::firstOrCreate(
            ['email' => 'joao.silva@fmp.edu.br'],
            [
                'nome'            => 'João da Silva',
                'cpf'             => '007.836.669-00',
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

    private function seedAlunosECertificadosFaker(\Illuminate\Database\Eloquent\Collection $cursos, $categorias, User $coordenador): void
    {
        $this->command->info('Gerando Alunos e Certificados dinâmicos com Faker...');
        $faker = Faker::create('pt_BR');

        $titulosCertificados = [
            'Bootcamp Desenvolvedor Full Stack',
            'Workshop de Clean Architecture',
            'Semana Acadêmica de Tecnologia',
            'Curso de Python para Ciência de Dados',
            'Certificação AWS Cloud Practitioner',
            'Palestra: Inteligência Artificial no Mercado',
            'Curso de Scrum e Metodologias Ágeis',
            'Introdução ao React e Next.js',
            'Maratona de Programação',
            'Congresso Nacional de Tecnologia da Informação',
            'Minicurso de Segurança da Informação',
            'Desenvolvimento de APIs com Laravel',
            'Gestão de Projetos de Software',
            'Imersão em Banco de Dados NoSQL',
            'Workshop de UI/UX Design'
        ];

        $instituicoes = [
            'Alura', 
            'Udemy', 
            'Rocketseat', 
            'DIO (Digital Innovation One)', 
            'AWS Training', 
            'Microsoft Learn', 
            'Universidade FMP', 
            'Google Cloud Skills'
        ];

        for ($i = 0; $i < 10; $i++) {
            
            $primeiroNome = $faker->firstName();
            $sobrenome = $faker->lastName();
            $nomeCompleto = $primeiroNome . ' ' . $sobrenome;
            
            $emailFicticio = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $primeiroNome . '.' . $sobrenome)) . $faker->numberBetween(10, 99) . '@fmp.edu.br';

            // Escolhe um curso aleatório da coleção de todos os cursos
            $cursoAleatorio = $cursos->random();

            $aluno = User::firstOrCreate(
                ['email' => $emailFicticio],
                [
                    'nome'            => $nomeCompleto,
                    'cpf'             => $faker->unique()->cpf(false),
                    'data_nascimento' => $faker->date('Y-m-d', '2005-01-01'),
                    'matricula'       => $faker->unique()->numerify('2025####'),
                    'password'        => Hash::make('aluno123'),
                    'tipo'            => TipoUsuario::ALUNO,
                    'curso_id'        => $cursoAleatorio->id, // Usa o ID do curso aleatório
                    'fase'            => $faker->numberBetween(1, 8), // Aumentado até a 8ª fase para mais variedade
                ]
            );

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
                    'nome_certificado'         => $faker->randomElement($titulosCertificados),
                    'instituicao'              => $faker->randomElement($instituicoes),
                    'data_emissao'             => $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                    'carga_horaria_solicitada' => $cargaSolicitada,
                    'arquivo_url'              => 'certificados/dummy.pdf', 
                    'status'                   => $status,
                    'coordenador_id'           => $isAvaliado ? $coordenador->id : null,
                    'data_validacao'           => $isAvaliado ? Carbon::now()->subDays($faker->numberBetween(1, 30)) : null,
                    'observacao'               => $isAvaliado ? 'Análise realizada com sucesso. ' . $faker->sentence() : null,
                    'horas_validadas'          => $status === StatusCertificado::APROVADO ? $cargaSolicitada : 
                                                 ($status === StatusCertificado::REPROVADO ? 0 : 
                                                 ($isAvaliado ? $faker->numberBetween(1, $cargaSolicitada) : null)),
                ]);
            }
        }
    }
}
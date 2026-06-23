# SHC API - Sistema de Horas Complementares 🎓

Uma API RESTful desenvolvida em **Laravel** para o gerenciamento, submissão e validação de horas complementares de estudantes de ensino superior (como no curso de Análise e Desenvolvimento de Sistemas e outros). O sistema conecta Alunos, Coordenadores, Secretaria e Administradores em um fluxo simplificado e auditável.

---

## 🚀 Principais Funcionalidades

* **Autenticação e Autorização:** Login seguro utilizando **Laravel Sanctum** com controle de acesso baseado em papéis (RBAC - Aluno, Coordenador, Secretaria, Administrador).
* **Gestão de Certificados:**
    * Alunos podem submeter certificados em PDF, selecionando a categoria e a carga horária solicitada.
    * Coordenadores avaliam os envios de seus respectivos cursos (Aprovar, Reprovar, Aprovar com Ressalvas).
* **Acompanhamento de Progresso:** Dashboard de progresso para os alunos, exibindo horas validadas contra as horas necessárias para a conclusão do curso, separadas por categoria.
* **Auditoria Integrada:** Rastreamento automático de todas as alterações (Criação, Atualização, Exclusão) em Usuários e Certificados através de *Observers* (`AuditObserver`), registrando quem fez a alteração, endereço de IP e os valores antigos/novos.
* **Gestão Acadêmica:** CRUD completo para Cursos, Categorias de Horas e Usuários (com suporte a importação em lote via JSON).
* **Utilitário de Contexto:** Comando customizado `php artisan app:bundle-context` para gerar um consolidado da base de código em Markdown.

---

## 🛠️ Tecnologias e Arquitetura

* **Linguagem:** PHP 8+
* **Framework:** Laravel
* **Banco de Dados:** Suporte nativo a SQLite, MySQL e PostgreSQL.
* **Autenticação:** Laravel Sanctum (Tokens de Acesso Pessoal / Stateful SPA).
* **Armazenamento:** Local Storage (configurado para uploads de PDFs e Avatares na pasta pública).
* **Padrões de Projeto:**
    * *API Resources:* Transformação robusta de dados de saída (ex: `UserResource`, `CertificadoResource`).
    * *Enums:* Tipagem estrita para Status (`StatusCertificado`) e Perfis (`TipoUsuario`).
    * *Policies/Gates:* Lógica de permissões centralizada no `AppServiceProvider` e `AuthServiceProvider`.

---

## ⚙️ Instalação e Configuração Local

Siga os passos abaixo para rodar o ambiente de desenvolvimento:

**1. Clone o repositório e instale as dependências**
```bash
git clone <url-do-repositorio>
cd shc-backend
composer install
```

**2. Configure as variáveis de ambiente**
Copie o arquivo de exemplo e ajuste o banco de dados (o padrão recomendado para teste local rápido é o SQLite).
```bash
cp .env.example .env
php artisan key:generate
```

**3. Prepare o Banco de Dados**
O projeto conta com *Migrations* e *Seeders* já populados com dados de teste (Cursos como ADS, usuários em todos os perfis e certificados de exemplo).
```bash
php artisan migrate --seed
```

**4. Link de Storage**
Como a aplicação lida com o upload de PDFs (certificados) e imagens (avatares), crie o link simbólico para a pasta pública:
```bash
php artisan storage:link
```

**5. Inicie o servidor**
```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`.

---

## 🔑 Acesso Rápido (Dados do Seeder)

Ao rodar a *migration* com as *seeds*, os seguintes usuários são criados automaticamente para testes (senha padrão para todos: `admin123`, `sec123`, `coord123`, `aluno123`):

| Perfil | E-mail |
| :--- | :--- |
| **Administrador** | admin@fmp.edu.br |
| **Secretaria** | secretaria@fmp.edu.br |
| **Coordenador (ADS)** | coord.ads@fmp.edu.br |
| **Aluno (ADS)** | aluno@fmp.edu.br |

---

## 📡 Endpoints da API

Abaixo está um resumo das principais rotas disponíveis. *Todas as rotas (exceto o login) exigem um Bearer Token via header `Authorization`.*

### Autenticação
* `POST /api/auth/login` - Autentica usuário e retorna token.
* `POST /api/auth/logout` - Revoga o token atual.
* `POST /api/auth/change-password` - Altera a senha do usuário logado.

### Certificados
* `GET /api/certificados` - Lista certificados (visão filtrada pelo papel do usuário).
* `POST /api/certificados` - *(Aluno)* Submete um novo certificado.
* `GET /api/certificados/{id}` - Detalhes do certificado.
* `PUT /api/certificados/{id}` - *(Aluno)* Edita certificado (apenas se status = ENTREGUE).
* `PATCH /api/certificados/{id}/avaliar` - *(Coordenador)* Avalia e insere horas validadas.

### Usuários & Progresso
* `GET /api/usuarios` - Lista usuários do sistema.
* `POST /api/usuarios` - Cria um novo usuário.
* `GET /api/usuarios/{id}/progresso` - Retorna a contagem de horas validadas vs. necessárias.
* `POST /api/usuarios/avatar` - Atualiza a foto de perfil do usuário logado.

### Parametrizações (Admin)
* `CRUD /api/cursos` - Gestão dos cursos ofertados.
* `CRUD /api/categorias` - Gestão das categorias de atividades.
* `GET/PUT /api/configuracoes` - Sistema chave/valor para configurações globais.

---

## 🛡️ CORS
A API está configurada para aceitar requisições do Live Server e dos domínios de frontend designados (ex: GitHub Pages, localhost:5500, localhost:3000, ngrok). Se for subir em um novo domínio, adicione-o na chave `allowed_origins` no arquivo `config/cors.php`.
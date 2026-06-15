# MPG Academy - memoria tecnica para Codex

## Visao geral
- Projeto PHP tradicional rodando em XAMPP, sem framework.
- Front controller em `index.php`.
- `ROOT` aponta para a raiz do projeto e `config/app.php` define `BASE_URL`, `ADMIN_BASE_URL`, `APP_ENV` e `APP_IS_LOCAL`.
- Rotas publicas usam `pages/{rota}/index.php`; rotas admin usam `admin/pages/{subrota}/index.php`.
- A URL e lida de `$_GET['url']`, provavelmente via `.htaccess`.

## Areas do sistema
- Site publico: home, quem somos, turmas/valores, cadastro, area do aluno, perfil, mensalidades, treinos, comunicados, assinaturas e patrocinio.
- Area do aluno: usa `$_SESSION['aluno']` e endpoints em `services/site`.
- Admin: usa `$_SESSION['usuario']`, paginas em `admin/pages`, endpoints em `admin/services`.
- Professores entram pelo mesmo login do admin, mas recebem `nivel_acesso = professor` e sao redirecionados para a area propria.

## Autenticacao e permissoes
- `admin/includes/auth_check.php` exige usuario logado e separa acesso de professor versus admin/editor/leitor.
- Professores podem acessar principalmente: `area-professor`, `meus-pagamentos`, `prof-turmas`, `meu-contrato`, `minhas-aulas`, `minha-frequencia`.
- Admin/editor/leitor sao redirecionados para `admin/inicio` se tentarem acessar area de professor.
- O login admin primeiro consulta `admin_usuarios`; se falhar, consulta `professores`.
- Login de aluno fica em `services/site/student_login.php`.

## Banco e dados
- Conexao PDO centralizada em `config/database.php` via `getDbConnection()`.
- Config local usa MySQL `localhost`, banco `mpgacademy_mpg_db`, usuario `root`, senha vazia.
- Variaveis de ambiente `MPG_DB_HOST`, `MPG_DB_NAME`, `MPG_DB_USER`, `MPG_DB_PASS` sobrescrevem defaults.
- Tabelas recorrentes: `alunos`, `admin_usuarios`, `professores`, `quadras`, `quadra_horarios`, `quadra_documentos`, `turmas`, `turma_horarios`, `turma_alunos`, `turma_treinos`, `aulas_experimentais`, `alunos_teste`, `fila_espera`, `mensalidades`, `comunicados`, `configuracoes`, `mobile_tokens`, `notificacoes_log`, `emails_notificacao`, `patrocinadores`, `lancamentos_financeiros`, `dividas`, `parcelas_dividas`, `dividas_anexos`, `professor_turmas`, `professor_pagamentos`, `professor_faltas`, `professor_aulas_concluidas`, `professor_contratos`, `termo_assinaturas`.
- `auth_check.php` gera mensalidades automaticamente uma vez por dia por sessao para usuarios nao-professores.

## APIs e seguranca
- Endpoints retornam JSON e normalmente chamam `config/api_security.php`.
- `validateApiAccess($ALLOWED_ORIGINS)` valida `Origin`/`Referer`, libera metodos `POST, GET, OPTIONS` e responde preflight.
- Muitos services verificam metodo HTTP e sessao manualmente.
- Evitar expor ou copiar credenciais: existem segredos reais em configs de banco, email e Mercado Pago.

## Frontend
- jQuery e maskedinput sao carregados globalmente em `includes/scripts.php` e `admin/includes/scripts.php`.
- `scripts/common.js` controla menu mobile, modal de login do aluno e dropdown do aluno.
- `admin/scripts/common.js` controla sidebar mobile/admin.
- Paginas costumam incluir seu JS proprio no final com cache busting por `time()`.

## Estilos e build
- Less publico: entrypoint `styles/style.min.less`, saida `styles/style.min.css`.
- Less admin: entrypoint `admin/styles/style.min.less`, saida `admin/styles/style.min.css`.
- `package.json`: `npm run dev` roda Gulp.
- `gulpfile.js`: compila Less publico/admin, minifica CSS e sobe BrowserSync proxy para `localhost/mpg_academy`.
- Fontes publicas usam Inter; admin usa Montserrat e Great Vibes.

## Integracoes
- Mercado Pago em `config/mercadopago.php`; modo teste vem de `configuracoes.pagamento_modo_teste`.
- Email usa PHPMailer/vendor e config em `config/mail.php`.
- WhatsApp/Z-API em `services/whatsapp` e crons em `cron/`.
- Mobile usa endpoints como `mobile_login.php`, `mobile_auth.php`, `get_mensalidades_mobile.php`, `get_comunicados_mobile.php`, `upload_foto_mobile.php`.

## Convencoes importantes
- Manter padrao de includes com `ROOT . '/...'`.
- Usar `BASE_URL`/`ADMIN_BASE_URL` para assets, links e endpoints.
- Para novas paginas, criar pasta `pages/nome/index.php` ou `admin/pages/nome/index.php`, Less correspondente e importar no entrypoint correto.
- Para novos endpoints JSON, seguir padrao: header JSON, `api_security.php`, validar metodo, validar sessao quando necessario, `getDbConnection()`, respostas `success/message`.
- O projeto contem textos com mojibake em varios arquivos; nao fazer reencoding/correcao ampla sem pedido explicito.
- Ao editar CSS, recompilar o CSS minificado correspondente.

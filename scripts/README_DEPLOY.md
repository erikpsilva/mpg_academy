# Deploy da MPG Academy

Publicação por FTP na KingHost, direto do terminal. Sobe **só o que mudou**.

```bash
npm run deploy
```

Isso compila o CSS (gulp), lista o que mudou desde a última publicação, pede confirmação e
envia para `/www`.

## Comandos

| Comando | O que faz |
|---|---|
| `npm run deploy` | Compila o CSS, mostra a lista e publica (com confirmação). |
| `npm run deploy:check` | Só mostra o que subiria. Não conecta pra escrever nada. |
| `npm run deploy:secrets` | Envia o arquivo de senhas para **fora** do `/www`. |
| `npm run build` | Só compila o CSS, sem publicar. |

Opções extras (depois de `--`):

```bash
npm run deploy -- --yes                       # sem a pergunta de confirmação
npm run deploy -- --only config admin/pages   # publica só esses caminhos
npm run deploy -- --all                       # reenvia tudo, ignorando o histórico
npm run deploy -- --delete                    # apaga no servidor o que foi apagado aqui
npm run deploy -- --baseline                  # marca tudo como publicado SEM enviar
npm run deploy -- --verbose                   # mostra o diálogo FTP inteiro
```

`--baseline` serve quando você acabou de subir os arquivos por fora (FileZilla) e quer que o
próximo `npm run deploy` mande só dali pra frente. Ele confia que o servidor está igual ao
local — se não estiver, use `--all` uma vez.

## Como ele sabe o que mudou

Cada arquivo publicado tem o hash guardado em `.deploy-state.json` (fora do Git). Na rodada
seguinte, só sobe quem tem hash diferente. Se a conexão cair no meio, o que já foi fica
registrado: rodar de novo continua de onde parou.

## O que nunca sobe

- `uploads/` e `storage/` — são dados de produção (fotos, contratos, logs). O que está aqui
  é cópia velha, e sobrescrever apagaria arquivo de gente de verdade.
- `node_modules/`, `.git/`, `doc/`, `.claude/`
- `*.sql`, `*.log`, `*.less` (o que vai é o CSS compilado), `error_log`
- a própria ferramenta de deploy e qualquer `mpg_secrets*.php`

## Senhas

### Do FTP

Ficam em `.env.deploy`, na raiz do projeto, **fora do Git** (o repositório é público).
Modelo em `scripts/deploy.example.env`.

A conexão é FTPS com verificação de certificado ligada, e a KingHost exige dois detalhes
pra isso fechar:

- **`FTP_HOST` é o nome do servidor** (`web2f01.uni5.net`), não `ftp.mpgacademy.com.br`. O
  certificado deles é o coringa `*.uni5.net`, então é o nome do servidor que bate. Pra
  descobrir o seu: `nslookup ftp.mpgacademy.com.br`.
- **O Node roda com `--use-system-ca`** (já está nos scripts do `package.json`). O servidor
  não manda a cadeia completa do certificado, e essa opção deixa o Node completar pelo
  repositório de certificados do Windows.

Se aparecer `unable to verify the first certificate` ou `does not match certificate's
altnames`, é um desses dois — nunca desligue a verificação pra "resolver": isso entregaria
a senha do FTP pra quem estiver no meio do caminho.

### Do sistema (banco, Mercado Pago, Z-API)

Não estão em nenhum arquivo dentro de `/www`. Elas vivem em `mpg_secrets.php`, um nível
**acima** da pasta pública:

```
/mpg_secrets.php     ← senhas (o Apache não serve, o Git não vê)
/www/                ← o site
```

Quem lê é `config/segredos.php`, que busca nesta ordem: variável de ambiente `MPG_<CHAVE>`,
depois o arquivo acima, depois o padrão de desenvolvimento do próprio código.

Os dois arquivos moram fora da pasta do projeto, lado a lado com ela:

| Arquivo | Para quê |
|---|---|
| `C:\xampp\htdocs\mpg_secrets.php` | máquina local (aponta pro XAMPP) |
| `C:\xampp\htdocs\mpg_secrets.prod.php` | o que `npm run deploy:secrets` envia |

Para trocar uma senha em produção: edite `mpg_secrets.prod.php` e rode
`npm run deploy:secrets`. Não precisa republicar o site.

Se o arquivo sumir do servidor, o sistema não "continua com senha vazia" em silêncio: o log
de erro diz exatamente qual chave falta e onde ele procurou.

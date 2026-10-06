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
| `npm run deploy:verificar` | Confere o servidor inteiro contra o projeto e marca o que estiver fora de dia. |
| `npm run deploy:tudo` | Reenvia tudo, sem comparar nada. O martelo, pra quando quiser certeza absoluta. |
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

**Quem decide é o servidor.** Antes de enviar qualquer coisa, o script lista o que está
publicado e compara arquivo por arquivo: o que falta, o que chegou incompleto e o que foi
editado aqui depois do último envio. O histórico local (`.deploy-state.json`, fora do Git)
entra só como reforço, pra pegar a edição que não mudou o tamanho do arquivo.

Isso existe porque confiar só no histórico local já deu errado: um upload caiu pela metade,
o script marcou como publicado, e metade de uma mudança ficou no ar. Hoje:

1. cada arquivo é conferido pelo tamanho logo depois de subir — se não bater, reenvia (até 4 tentativas);
2. no fim, o servidor inteiro é reconferido. Se sobrar diferença, ela aparece na tela e o comando termina com erro;
3. se a conexão cair, o que já subiu fica registrado e a rodada seguinte continua de onde parou.

Arquivo enviado pelo FileZilla conta como publicado: o modo automático dele troca CRLF por
LF, deixando o arquivo alguns bytes menor, e a conferência aceita as duas formas.

## O que nunca sobe

- `uploads/`, `storage/`, `images/jogadores/` e `images/alunos/` — são dados de produção
  (fotos que alunos e jogadores enviaram pelo site, contratos, logs). O que está aqui é cópia
  velha e incompleta; sobrescrever ou apagar destruiria arquivo de gente de verdade, sem
  backup. Elas nem entram na comparação.
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

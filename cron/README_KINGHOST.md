# Configuração dos Crons — KingHost

Substitui o `README_CPANEL.md`, que valia para o servidor antigo.

**A diferença que muda tudo:** o cPanel executava os scripts por linha de comando
(`php /caminho/arquivo.php`). A KingHost executa **abrindo a URL do script por HTTP**,
mandando junto o header `X-Cron-Auth` do painel. Você agenda endereços, não comandos.

---

## Passo 0 — contratar o pacote

O cronjob da KingHost é pago: **R$ 7,90 por pacote de 20 tarefas agendadas**. Este projeto
usa **8 tarefas** (7 scripts, sendo a cobrança automática duas vezes por dia), então um
pacote basta.

Painel → Gerenciar mpgacademy.com.br → **Configuração de Cronjob** → Contratar pacote.

---

## Passo 1 — gravar o token de segurança no banco

Os scripts ficam dentro da pasta pública. Sem proteção, qualquer pessoa que descobrisse o
endereço dispararia um envio em massa de WhatsApp ou uma rodada de cobrança no cartão.

A proteção é o header do painel. Copie o valor que aparece em **HEADER DE VALIDAÇÃO** e
grave no banco de produção:

```sql
INSERT INTO configuracoes (chave, valor)
VALUES ('cron_auth_token', 'COLE_AQUI_O_TOKEN_DO_PAINEL')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
```

O token fica no banco e não no código porque o repositório é público. Se um dia você
trocar o token no painel, é só rodar esse comando de novo com o valor novo.

`cron/_auth.php` compara o header recebido com esse valor. Sem header, ou com header
errado, o script responde 404 e não executa nada. Execução por linha de comando (SSH)
passa direto, sem token.

---

## Passo 2 — agendar as 8 tarefas

No formulário, escolha minuto/hora/dia e informe a **URL** do script.

### Lembretes de aula experimental

| Quando | URL |
|---|---|
| Todo dia **08:00** | `https://www.mpgacademy.com.br/cron/wpp_lembrete_3dias.php` |
| Todo dia **08:05** | `https://www.mpgacademy.com.br/cron/wpp_lembrete_2dias.php` |
| Todo dia **18:00** | `https://www.mpgacademy.com.br/cron/wpp_lembrete_amanha.php` |
| Todo dia **06:00** | `https://www.mpgacademy.com.br/cron/wpp_lembrete_dia_aula.php` |

> O de 3 e o de 2 dias rodavam ambos às 08:00 no servidor antigo. Separei em 08:00 e 08:05
> por causa do teto de 60 segundos: duas rodadas ao mesmo tempo disputam recurso e a
> chance de as duas estourarem aumenta.

> O "dia da aula" deve rodar cerca de 3h antes da turma que começa mais cedo. Turmas às
> 09h → 06:00. Se um dia tiver turma às 07h, mude para 04:00.

### Mensalidades

| Quando | URL |
|---|---|
| Todo dia **08:10** | `https://www.mpgacademy.com.br/cron/wpp_mensalidade.php` |

Cobre os três gatilhos numa rodada só: 5 dias antes, no vencimento, e a cada 2 dias em
atraso.

### Cobrança automática (cartão salvo)

| Quando | URL |
|---|---|
| Todo dia **07:00** | `https://www.mpgacademy.com.br/cron/cobranca_automatica.php` |
| Todo dia **15:00** | `https://www.mpgacademy.com.br/cron/cobranca_automatica.php` |

> A rodada das 07h vem antes do lembrete das 08h10 de propósito: quem já foi cobrado no
> cartão de manhã não recebe cobrança por WhatsApp no mesmo dia. Se a rodada da manhã
> falhar (cartão sem saldo, instabilidade da API), a das 15h tenta de novo.
> `cobranca_automatica_log` impede cobrar a mesma mensalidade duas vezes com sucesso no
> mesmo dia.

### Conciliação de pagamentos (Mercado Pago)

| Quando | URL |
|---|---|
| A cada **10 minutos** | `https://www.mpgacademy.com.br/cron/conciliar_pagamentos.php` |

> Confirma PIX/cartão aprovados no MP cujo aviso (webhook) não chegou — ex.: a pessoa pagou
> o PIX do Bate Bola e fechou a tela. Sem ele, o pagamento fica pendente e o jogador fora da
> lista. Termina em até ~40s e é seguro repetir: só confirma o que o MP diz que está aprovado.

### Lembrete de treino

| Quando | URL |
|---|---|
| Todo dia **05:00** | `https://www.mpgacademy.com.br/cron/wpp_lembrete_treino.php` |

> Envia 4h antes para turmas que começam às 09h. Turmas às 07h → 03:00.
> ⚠️ Leia o aviso do timeout abaixo antes de agendar este.

---

## Passo 3 — testar

Abra a URL de um cron no navegador. **Deve dar 404** — é a prova de que a trava está
funcionando e que ninguém de fora consegue disparar.

Para testar de verdade, com o header, use o terminal (ou peça ao suporte):

```
curl -i -H "X-Cron-Auth: SEU_TOKEN" https://www.mpgacademy.com.br/cron/wpp_lembrete_3dias.php
```

Resposta esperada: `HTTP 200` e uma linha como `Lembretes 3 dias: 0 enviados.`

Se der 404 com o header correto, o token no banco não bate com o do painel.

**Confirme que enviou de verdade:** veja se a pasta `storage/whatsapp_teste/` foi criada
no servidor. Se ela existir com arquivos dentro, o sistema rodou como "ambiente local" e
gravou as mensagens em disco em vez de enviar — ninguém recebeu. Rodando por HTTP isso não
deve acontecer, porque o endereço do site identifica o ambiente como produção.

---

## O limite de 60 segundos

A KingHost derruba a tarefa em **60 segundos**, tenta de novo até **4 vezes**, com 10
segundos entre as tentativas.

É apertado aqui. Hoje são 60 mensalidades pendentes/atrasadas e 51 alunos ativos, e cada
envio pela Z-API pode levar até 15 segundos quando a API demora. Uma rodada que precise
falar com 60 pessoas não termina em 60 segundos.

O que acontece se estourar:

| Cron | Se for cortado no meio |
|---|---|
| `wpp_mensalidade.php` | Seguro. Grava em `notificacoes_log` a cada envio, então a retentativa continua de onde parou. |
| `wpp_lembrete_3dias` / `2dias` / `amanha` / `dia_aula` | Seguros. Mesma proteção, via `lembrete_teste_log`. |
| `cobranca_automatica.php` | Seguro contra cobrança dupla, via `cobranca_automatica_log`. |
| `conciliar_pagamentos.php` | Seguro. Só confirma o que ainda está em aberto; o que já foi confirmado é ignorado. |
| **`wpp_lembrete_treino.php`** | ⚠️ **Não tem log de controle.** Se estourar e a KingHost repetir, quem já recebeu **recebe de novo** — até 4 vezes. |

**Recomendação:** agende o lembrete de treino por último e acompanhe a primeira semana. Se
ele estourar o tempo, o caminho é dar a ele o mesmo controle de log que os outros já têm.

---

## Disparos imediatos (não precisam de cron)

- **Confirmação de aula experimental** → `admin/services/add_aluno_teste.php`
- **Novo comunicado publicado** → `admin/services/save_comunicado.php`

---

## Observações

- Confirme que o plano da **Z-API está ativo** (z-api.io). Sem isso os envios falham e o
  log ainda registra a tentativa.
- Os tipos de log de mensalidade (`wpp_5dias`, `wpp_vencimento`, `wpp_atraso`) ficam em
  `notificacoes_log`.
- Em ambiente local (Windows/XAMPP) as mensagens continuam sendo gravadas em
  `storage/whatsapp_teste/` em vez de enviadas — é o comportamento desejado no
  desenvolvimento.

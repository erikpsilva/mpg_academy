# Configuração dos Crons — MPG Academy WhatsApp

## Como configurar no cPanel

1. Acesse **cPanel → Cron Jobs**
2. Configure cada linha abaixo (substitua `SEU_USUARIO` pelo usuário do servidor)

---

## Crons necessários

### Lembretes de aula experimental

**3 dias antes** — executa todo dia às 08:00
```
0 8 * * * php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_3dias.php >> /home/SEU_USUARIO/logs/wpp_3dias.log 2>&1
```

**No dia da aula** — executa todo dia às 06:00 (3h antes das aulas das 09h)
```
0 6 * * * php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_dia_aula.php >> /home/SEU_USUARIO/logs/wpp_dia_aula.log 2>&1
```
> Ajuste o horário conforme o início mais cedo das suas turmas. Ex: turmas das 07h → executar às 04h.

---

### Mensalidades

**Todo dia às 08:00** — cobre os 3 gatilhos (5 dias antes, vencimento, atraso a cada 2 dias)
```
0 8 * * * php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_mensalidade.php >> /home/SEU_USUARIO/logs/wpp_mensalidade.log 2>&1
```

---

### Cobrança automática (cartão salvo)

**Todo dia às 07:00** — cobra mensalidades pendentes/atrasadas de alunos com pagamento automático ativado
```
0 7 * * * php /home/SEU_USUARIO/public_html/mpg_academy/cron/cobranca_automatica.php >> /home/SEU_USUARIO/logs/cobranca_automatica.log 2>&1
```
> Roda todo dia (não só perto do dia 5) pra também insistir em quem ficou atrasado. Nunca cobra a mesma mensalidade duas vezes no mesmo dia (controlado por `cobranca_automatica_log`). Recomenda-se rodar antes do `wpp_mensalidade.php` (08:00), assim quem já foi cobrado automaticamente não recebe lembrete de cobrança no mesmo dia.

---

### Lembrete de treino

**Todo dia às 05:00** — envia 4h antes para turmas que começam às 09h+
```
0 5 * * * php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_treino.php >> /home/SEU_USUARIO/logs/wpp_treino.log 2>&1
```
> Se tiver turmas às 07h, mude para 03h. Se as turmas mais cedo são às 08h, use 04h.

---

## Disparos imediatos (sem cron)

Estes são chamados automaticamente pelo PHP no momento do evento:

- **Confirmação de aula experimental** → `admin/services/add_aluno_teste.php` (já integrado)
- **Novo comunicado publicado** → `admin/services/save_comunicado.php` (já integrado)

---

## Observações

- Em ambiente **local** (localhost), as mensagens são salvas em `storage/whatsapp_teste/` em vez de enviadas.
- Certifique-se de que o **trial da Z-API foi renovado** (assinar plano em z-api.io).
- Os tipos de log para mensalidades (`wpp_5dias`, `wpp_vencimento`, `wpp_atraso`) são salvos em `notificacoes_log` — não haverá duplicatas.
- O lembrete de treino não usa log (é seguro enviar toda semana).

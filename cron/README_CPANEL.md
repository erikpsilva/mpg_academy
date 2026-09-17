# (obsoleto) Configuração dos Crons — cPanel

Este arquivo valia para o servidor antigo, que usava cPanel. O projeto está na KingHost,
que tem painel próprio, caminhos diferentes e um teto de 60 segundos por tarefa.

**Use o [README_KINGHOST.md](README_KINGHOST.md).**

Os comandos que estavam aqui não funcionam mais: a KingHost não executa por linha de
comando. Ela **abre a URL do script por HTTP**, mandando o header `X-Cron-Auth`. Você
agenda endereços (`https://www.mpgacademy.com.br/cron/...`), não comandos
`php /home/USUARIO/public_html/...`.

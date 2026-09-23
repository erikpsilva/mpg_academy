<?php

/**
 * Segredos do sistema (banco, Mercado Pago, Z-API) — carregados de FORA da pasta pública.
 *
 * Por que isso existe: todo arquivo dentro de /www é servido pela web e vive num
 * repositório público. Senha escrita em qualquer arquivo daqui é senha publicada. Então o
 * código não guarda nenhum valor real: ele lê de um arquivo que mora um nível ACIMA de
 * /www, onde o Apache não alcança e o Git não enxerga.
 *
 * No servidor (KingHost):
 *   /mpg_secrets.php   ← o arquivo com as senhas (fora do /www)
 *   /www/...           ← o site inteiro
 *
 * Na máquina local, o mesmo desenho: C:\xampp\htdocs\mpg_secrets.php, ao lado (e fora) da
 * pasta do projeto. Se ele não existir, o sistema cai nos valores locais de
 * desenvolvimento — XAMPP com root sem senha, por exemplo.
 *
 * Ordem de busca de cada chave:
 *   1. variável de ambiente MPG_<CHAVE>  (útil pra cron e pra CI)
 *   2. arquivo de segredos fora da pasta pública
 *   3. o padrão que o próprio código passar (só valores de desenvolvimento)
 *
 * O arquivo de segredos é um PHP que devolve um array:
 *   <?php return ['DB_PASS' => '...', 'MP_ACCESS_TOKEN_PROD' => '...'];
 * É PHP de propósito: se alguém conseguir servir o arquivo por engano, o resultado é uma
 * página em branco em vez do conteúdo — o que não aconteceria com .env ou .json.
 */

/** Caminhos onde o arquivo de segredos pode estar, na ordem de preferência. */
function mpgSegredosCaminhos(): array
{
    $caminhos = [];

    // Definido explicitamente (cron por SSH, ambiente de teste, etc.).
    $doAmbiente = getenv('MPG_SECRETS_FILE');
    if (is_string($doAmbiente) && $doAmbiente !== '') {
        $caminhos[] = $doAmbiente;
    }

    // Um nível acima da raiz do projeto: /mpg_secrets.php quando o site está em /www.
    $raizProjeto = dirname(__DIR__);
    $caminhos[] = dirname($raizProjeto) . '/mpg_secrets.php';

    // Rede de segurança pra hospedagem que aninha mais um nível (public_html dentro da home).
    $caminhos[] = dirname(dirname($raizProjeto)) . '/mpg_secrets.php';

    return $caminhos;
}

/** Lê (uma vez por requisição) o arquivo de segredos. Ausente = array vazio. */
function mpgSegredos(): array
{
    static $segredos = null;
    if ($segredos !== null) return $segredos;

    $segredos = [];

    foreach (mpgSegredosCaminhos() as $caminho) {
        if (!is_file($caminho) || !is_readable($caminho)) continue;

        $conteudo = require $caminho;
        if (is_array($conteudo)) {
            $segredos = $conteudo;
            break;
        }

        error_log('[mpg-segredos] ' . $caminho . ' não devolveu um array — ignorado.');
    }

    return $segredos;
}

/**
 * Valor de um segredo.
 *
 * $padrao é só para desenvolvimento local. Em produção, chave sem valor devolve null e quem
 * chamou decide o que fazer — ver mpgSegredoObrigatorio().
 */
function segredo(string $chave, ?string $padrao = null): ?string
{
    $doAmbiente = getenv('MPG_' . $chave);
    if (is_string($doAmbiente) && $doAmbiente !== '') return $doAmbiente;

    $todos = mpgSegredos();
    if (isset($todos[$chave]) && $todos[$chave] !== '') return (string) $todos[$chave];

    return $padrao;
}

/**
 * Segredo sem o qual a operação não acontece (senha do banco, token do Mercado Pago).
 *
 * Falta de segredo em produção não pode virar "senha vazia" e um erro genérico três telas
 * adiante: o log diz exatamente qual chave falta e onde colocá-la.
 */
function mpgSegredoObrigatorio(string $chave): string
{
    $valor = segredo($chave);
    if ($valor !== null && $valor !== '') return $valor;

    error_log('[mpg-segredos] Falta a chave ' . $chave . ' no arquivo de segredos. '
            . 'Procurei em: ' . implode(', ', mpgSegredosCaminhos()));

    return '';
}

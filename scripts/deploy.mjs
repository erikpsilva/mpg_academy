/**
 * Deploy da MPG Academy por FTP (KingHost).
 *
 * Sobe SÓ o que mudou: guarda o hash de cada arquivo enviado em .deploy-state.json e
 * compara na rodada seguinte. Primeira execução manda tudo; depois disso um deploy comum
 * são alguns segundos e meia dúzia de arquivos.
 *
 * Credenciais NUNCA ficam aqui: o repositório é público. Elas vêm de .env.deploy (que está
 * no .gitignore) ou de variáveis de ambiente — ver scripts/deploy.example.env.
 *
 *   npm run deploy           → mostra o que mudou, pede confirmação e sobe
 *   npm run deploy:check     → só mostra o que subiria (não conecta pra escrever)
 *   npm run deploy:secrets   → envia mpg_secrets.prod.php para FORA do /www
 *   npm run deploy -- --yes  → sem confirmação (use quando já conferiu a lista)
 *   npm run deploy -- --all  → reenvia tudo, ignorando o estado anterior
 *   npm run deploy -- --only admin/pages/uniformes config/uniformes.php
 *   npm run deploy -- --delete → apaga no servidor o que foi apagado aqui
 */

import { Client } from 'basic-ftp';
import { createHash } from 'node:crypto';
import { createInterface } from 'node:readline/promises';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RAIZ = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const ARQUIVO_ESTADO = path.join(RAIZ, '.deploy-state.json');

/**
 * O que nunca sobe.
 *
 * `uploads/` e `storage/` são dados de produção (fotos de alunos, contratos, logs): o que
 * está aqui é uma cópia velha, e sobrescrever apagaria arquivo de gente de verdade.
 * `vendor/` sobe porque o PHP precisa dele no servidor; como só vai o que mudou, isso
 * custa uma rodada só, na primeira vez.
 */
const IGNORAR_DIRS = new Set([
    'node_modules', '.git', '.claude', '.vscode', '.idea',
    'uploads',      // arquivos enviados pelos usuários — pertencem ao servidor
    'storage',      // logs e mensagens de teste geradas em runtime
    'doc',          // documentação interna
    'propostacomercial',
]);

const IGNORAR_ARQUIVOS = new Set([
    '.env', '.env.deploy', '.deploy-state.json', '.gitignore',
    'package.json', 'package-lock.json', 'gulpfile.js',
    'CODEX_MEMORY.md', 'README.md', 'error_log', 'nul',
    'composer.json', 'composer.lock',
    // Ferramenta de deploy: roda aqui, não tem o que fazer no servidor.
    'deploy.mjs', 'deploy.example.env', 'README_DEPLOY.md',
]);

// Nunca, em hipótese alguma, dentro da pasta pública.
const NUNCA_SUBIR = /(^|\/)mpg_secrets[^/]*\.php$/i;

const IGNORAR_EXTENSOES = new Set(['.sql', '.log', '.less', '.zip', '.rar', '.bak']);

// ── Configuração ──────────────────────────────────────────────────────────────

function lerEnvDeploy() {
    const caminho = path.join(RAIZ, '.env.deploy');
    if (!fs.existsSync(caminho)) return {};

    const conf = {};
    for (const linha of fs.readFileSync(caminho, 'utf8').split(/\r?\n/)) {
        const limpa = linha.trim();
        if (!limpa || limpa.startsWith('#')) continue;
        const i = limpa.indexOf('=');
        if (i === -1) continue;
        conf[limpa.slice(0, i).trim()] = limpa.slice(i + 1).trim().replace(/^["']|["']$/g, '');
    }
    return conf;
}

const envArquivo = lerEnvDeploy();
const conf = (chave, padrao = '') => process.env[chave] ?? envArquivo[chave] ?? padrao;

// FTPS explícito com verificação de certificado ligada — é ela que garante que a senha do
// FTP está indo pro servidor certo, e não pra quem estiver no meio do caminho.
const FTP = {
    host:    conf('FTP_HOST'),
    user:    conf('FTP_USER'),
    password: conf('FTP_PASS'),
    secure:  conf('FTP_SECURE', 'true') !== 'false',
    secureOptions: { servername: conf('FTP_HOST') },
};

// Pasta pública no servidor. Na KingHost costuma ser /www; fica configurável porque isso
// muda de hospedagem pra hospedagem.
const DIR_REMOTO = conf('FTP_DIR', '/www');

// O arquivo de segredos vive um nível ACIMA da pasta pública: lá o Apache não serve e o
// repositório não alcança. É o que guarda senha do banco, token do Mercado Pago e da Z-API.
const DIR_SEGREDOS   = conf('FTP_SECRETS_DIR', '/');
const NOME_SEGREDOS  = conf('SECRETS_REMOTE_NAME', 'mpg_secrets.php');
const ORIGEM_SEGREDOS = conf('SECRETS_LOCAL_FILE', path.join(path.dirname(RAIZ), 'mpg_secrets.prod.php'));

// ── Argumentos ────────────────────────────────────────────────────────────────

const args      = process.argv.slice(2);
const temFlag   = (f) => args.includes(f);
const soChecar  = temFlag('--check') || temFlag('--dry-run');
const semPergunta = temFlag('--yes') || temFlag('-y');
const reenviarTudo = temFlag('--all');
const apagarRemoto = temFlag('--delete');

const soSegredos = temFlag('--secrets');
const soLimpar   = temFlag('--limpar');

/**
 * Coisas que não deveriam existir dentro da pasta pública.
 *
 * Chegaram lá em publicações manuais antigas, quando a pasta inteira era arrastada pelo
 * FileZilla. Não são arquivos do site: são o histórico do Git (que carrega todo o código e
 * as senhas antigas), anotações internas e log de erro — coisas que, servidas pela web,
 * contam pra qualquer um como o sistema funciona por dentro.
 */
const LIXO_REMOTO = [
    { caminho: '.git',              tipo: 'dir',  motivo: 'histórico do Git — expõe o código e senhas antigas' },
    { caminho: '.claude',           tipo: 'dir',  motivo: 'configuração local de ferramenta' },
    { caminho: 'node_modules',      tipo: 'dir',  motivo: 'dependências de build, inúteis no servidor' },
    { caminho: 'doc',               tipo: 'dir',  motivo: 'documentação interna' },
    { caminho: 'error_log',         tipo: 'file', motivo: 'log de erros do PHP, legível pela web' },
    { caminho: 'CODEX_MEMORY.md',   tipo: 'file', motivo: 'anotações técnicas internas' },
    { caminho: 'README.md',         tipo: 'file', motivo: 'documentação interna' },
    { caminho: 'composer.json',     tipo: 'file', motivo: 'lista de dependências' },
    { caminho: 'composer.lock',     tipo: 'file', motivo: 'lista de dependências' },
    { caminho: 'package.json',      tipo: 'file', motivo: 'lista de dependências' },
    { caminho: 'package-lock.json', tipo: 'file', motivo: 'lista de dependências' },
    { caminho: 'gulpfile.js',       tipo: 'file', motivo: 'script de build' },
    { caminho: '.gitignore',        tipo: 'file', motivo: 'arquivo de desenvolvimento' },
];

// Marca tudo como "já publicado" sem enviar nada. Serve quando o servidor acabou de receber
// os arquivos por fora (FileZilla, por exemplo) e você só quer que o próximo deploy mande
// daí pra frente. Use sabendo que ele CONFIA que o servidor está igual ao local.
const soMarcar = temFlag('--baseline');

const indiceOnly = args.indexOf('--only');
const filtros = indiceOnly === -1
    ? []
    : args.slice(indiceOnly + 1).filter((a) => !a.startsWith('--')).map((a) => a.replace(/\\/g, '/'));

// ── Varredura local ───────────────────────────────────────────────────────────

function deveIgnorar(relativo, nome, ehDir) {
    if (ehDir) return IGNORAR_DIRS.has(nome);
    // Arquivo de segredos só existe fora do projeto; se alguém copiar um pra dentro por
    // engano, ele não sobe junto com o site — é o erro que publicaria todas as senhas.
    if (NUNCA_SUBIR.test(relativo)) return true;
    if (IGNORAR_ARQUIVOS.has(nome)) return true;
    if (IGNORAR_EXTENSOES.has(path.extname(nome).toLowerCase())) return true;
    if (nome.startsWith('__tmp')) return true;          // harness de teste local
    if (nome === 'Thumbs.db' || nome === '.DS_Store') return true;
    return false;
}

function listarArquivos(dir = RAIZ, prefixo = '') {
    const saida = [];
    for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
        const relativo = prefixo ? `${prefixo}/${item.name}` : item.name;
        if (deveIgnorar(relativo, item.name, item.isDirectory())) continue;

        if (item.isDirectory()) {
            saida.push(...listarArquivos(path.join(dir, item.name), relativo));
        } else if (item.isFile()) {
            saida.push(relativo);
        }
    }
    return saida;
}

const hashArquivo = (relativo) =>
    createHash('sha1').update(fs.readFileSync(path.join(RAIZ, relativo))).digest('hex');

function lerEstado() {
    if (reenviarTudo || !fs.existsSync(ARQUIVO_ESTADO)) return {};
    try {
        return JSON.parse(fs.readFileSync(ARQUIVO_ESTADO, 'utf8')).arquivos ?? {};
    } catch {
        return {};
    }
}

function salvarEstado(arquivos) {
    fs.writeFileSync(
        ARQUIVO_ESTADO,
        JSON.stringify({ atualizado_em: new Date().toISOString(), arquivos }, null, 2)
    );
}

// ── Execução ──────────────────────────────────────────────────────────────────

function abortar(mensagem) {
    console.error(`\n✖ ${mensagem}\n`);
    process.exit(1);
}

/**
 * Erro de certificado é o tropeço mais provável aqui, e a mensagem do Node não diz o que
 * fazer. São dois motivos, os dois de configuração e nenhum de "desligar a segurança":
 * o certificado da KingHost é o coringa *.uni5.net (então FTP_HOST tem que ser o nome do
 * servidor) e vem sem a cadeia completa (daí o --use-system-ca nos scripts do package.json).
 */
function dicaCertificado(erro) {
    const m = String(erro?.message ?? '');
    if (!/certificate|altnames|self.signed/i.test(m)) return '';

    return '\n\n  Isso é certificado, não senha:'
         + '\n   • FTP_HOST precisa ser o nome do SERVIDOR (ex.: web2f01.uni5.net),'
         + '\n     não ftp.seudominio.com.br — descubra com: nslookup ftp.seudominio.com.br'
         + '\n   • rode pelos scripts do package.json, que já passam --use-system-ca ao Node.';
}

const casaComFiltro = (relativo) =>
    filtros.length === 0 || filtros.some((f) => relativo === f || relativo.startsWith(f.replace(/\/$/, '') + '/'));

/**
 * Envia o arquivo de segredos para fora da pasta pública.
 *
 * Vai separado do deploy normal porque muda quase nunca e porque o destino é outro: uma
 * pasta que o site inteiro não pode alcançar. Mandar junto com o resto seria a maneira mais
 * fácil de um dia ele cair dentro do /www por engano.
 */
async function enviarSegredos() {
    if (!fs.existsSync(ORIGEM_SEGREDOS)) {
        abortar(`Não achei o arquivo de segredos em ${ORIGEM_SEGREDOS}.\n`
              + '  Ele mora FORA do projeto de propósito — copie mpg_secrets.prod.php para lá.');
    }

    const destino = path.posix.join(DIR_SEGREDOS, NOME_SEGREDOS);

    console.log(`\nSegredos: ${ORIGEM_SEGREDOS}\n      →   ${FTP.host}${destino}  (fora do ${DIR_REMOTO})\n`);

    if (soChecar) {
        console.log('--check: nada foi enviado.\n');
        return;
    }

    if (!semPergunta) {
        const rl = createInterface({ input: process.stdin, output: process.stdout });
        const r = (await rl.question('Enviar o arquivo de segredos para produção? [s/N] ')).trim().toLowerCase();
        rl.close();
        if (r !== 's' && r !== 'sim' && r !== 'y') {
            console.log('Cancelado. Nada foi enviado.\n');
            return;
        }
    }

    const client = new Client(30_000);
    client.ftp.verbose = temFlag('--verbose');

    try {
        await client.access(FTP);
        await client.uploadFrom(ORIGEM_SEGREDOS, destino);
        console.log(`\n✔ Segredos publicados em ${FTP.host}${destino}\n`);
    } catch (erro) {
        abortar(`Não consegui enviar os segredos: ${erro.message}` + dicaCertificado(erro));
    } finally {
        client.close();
    }
}

/**
 * Apaga do servidor o que não é do site (ver LIXO_REMOTO).
 *
 * Confere o que existe antes de propor qualquer coisa, e só apaga com confirmação: é a
 * única parte do deploy que destrói arquivo em produção.
 */
async function limparRemoto() {
    const client = new Client(60_000);
    client.ftp.verbose = temFlag('--verbose');

    try {
        await client.access(FTP);

        const naRaiz = await client.list(DIR_REMOTO);
        const existentes = new Map(naRaiz.map((f) => [f.name, f]));
        const achados = LIXO_REMOTO.filter((item) => existentes.has(item.caminho));

        if (!achados.length) {
            console.log(`\n✔ Nada a limpar em ${DIR_REMOTO}.\n`);
            return;
        }

        console.log(`\nEncontrei em ${FTP.host}${DIR_REMOTO}:\n`);
        for (const item of achados) {
            console.log(`  - ${item.caminho}${item.tipo === 'dir' ? '/' : ''}  →  ${item.motivo}`);
        }

        if (soChecar) {
            console.log('\n--check: nada foi apagado.\n');
            return;
        }

        if (!semPergunta) {
            const rl = createInterface({ input: process.stdin, output: process.stdout });
            const r = (await rl.question(`\nApagar ${achados.length} item(ns) do servidor? [s/N] `)).trim().toLowerCase();
            rl.close();
            if (r !== 's' && r !== 'sim' && r !== 'y') {
                console.log('Cancelado. Nada foi apagado.\n');
                return;
            }
        }

        for (const item of achados) {
            const alvo = path.posix.join(DIR_REMOTO, item.caminho);
            try {
                if (item.tipo === 'dir') await client.removeDir(alvo);
                else                     await client.remove(alvo);
                console.log(`  ✖ ${item.caminho}`);
            } catch (e) {
                console.log(`  ! não consegui apagar ${item.caminho}: ${e.message}`);
            }
        }

        console.log('\n✔ Limpeza concluída.\n');
    } catch (erro) {
        abortar(`Falhou na limpeza: ${erro.message}` + dicaCertificado(erro));
    } finally {
        client.close();
    }
}

async function main() {
    if (!soChecar && (!FTP.host || !FTP.user || !FTP.password)) {
        abortar('Faltam as credenciais de FTP. Crie o arquivo .env.deploy na raiz '
              + '(veja scripts/deploy.example.env) ou exporte FTP_HOST, FTP_USER e FTP_PASS.');
    }

    if (soSegredos) return enviarSegredos();
    if (soLimpar)   return limparRemoto();

    const estadoAnterior = lerEstado();
    const locais = listarArquivos().filter(casaComFiltro);

    const hashes = {};
    const novos = [];
    const alterados = [];

    for (const relativo of locais) {
        const hash = hashArquivo(relativo);
        hashes[relativo] = hash;

        if (!(relativo in estadoAnterior)) novos.push(relativo);
        else if (estadoAnterior[relativo] !== hash) alterados.push(relativo);
    }

    // Some daqui, mas continua lá: o servidor não sabe que o arquivo foi apagado.
    const apagados = Object.keys(estadoAnterior)
        .filter((r) => casaComFiltro(r) && !locais.includes(r));

    const paraSubir = [...novos, ...alterados].sort();

    console.log(`\nMPG Academy — deploy para ${FTP.host || '(host não configurado)'}${DIR_REMOTO}`);
    console.log(`${locais.length} arquivos no projeto · ${novos.length} novos · ${alterados.length} alterados · ${apagados.length} apagados aqui\n`);

    if (paraSubir.length === 0 && apagados.length === 0) {
        console.log('Nada mudou desde o último deploy. ✔\n');
        return;
    }

    for (const r of novos)     console.log(`  + ${r}`);
    for (const r of alterados) console.log(`  ~ ${r}`);
    for (const r of apagados)  console.log(`  - ${r}${apagarRemoto ? ' (será apagado no servidor)' : ' (continua no servidor)'}`);

    if (soChecar) {
        console.log('\n--check: nada foi enviado.\n');
        return;
    }

    if (soMarcar) {
        salvarEstado({ ...estadoAnterior, ...hashes });
        console.log(`\n✔ ${paraSubir.length} arquivo(s) marcados como publicados, sem enviar nada.`);
        console.log('  O próximo deploy manda só o que mudar a partir de agora.\n');
        return;
    }

    if (!semPergunta) {
        const rl = createInterface({ input: process.stdin, output: process.stdout });
        const resposta = (await rl.question(`\nSubir ${paraSubir.length} arquivo(s) para produção? [s/N] `)).trim().toLowerCase();
        rl.close();
        if (resposta !== 's' && resposta !== 'sim' && resposta !== 'y') {
            console.log('Cancelado. Nada foi enviado.\n');
            return;
        }
    }

    const client = new Client(60_000);
    client.ftp.verbose = temFlag('--verbose');

    // Estado parcial: se a conexão cair no meio, o que já subiu fica registrado e a próxima
    // rodada continua de onde parou em vez de mandar tudo de novo.
    const estadoNovo = { ...estadoAnterior };
    let enviados = 0;

    // A KingHost derruba o canal de controle no meio de uma sequência longa de uploads
    // (ECONNRESET depois de algumas dezenas de arquivos). Reconectar e seguir é parte do
    // trabalho, não exceção: sem isso um deploy de 500 arquivos nunca termina de uma vez.
    async function conectar() {
        await client.access(FTP);
        await client.ensureDir(DIR_REMOTO);
        await client.cd('/');
    }

    const ehQuedaDeConexao = (e) =>
        /ECONNRESET|EPIPE|ETIMEDOUT|closed|Timeout|not connected/i.test(String(e?.message ?? ''));

    try {
        await conectar();

        let pastaAtual = null;

        for (const relativo of paraSubir) {
            const destino = `${DIR_REMOTO}/${relativo}`;
            const pastaRemota = path.posix.dirname(destino);

            // Três tentativas por arquivo: a primeira falha costuma ser a queda do canal, e
            // a seguinte já vai numa conexão nova.
            for (let tentativa = 1; ; tentativa++) {
                try {
                    if (pastaRemota !== pastaAtual) {
                        await client.ensureDir(pastaRemota);
                        await client.cd('/');
                        pastaAtual = pastaRemota;
                    }

                    await client.uploadFrom(path.join(RAIZ, relativo), destino);
                    break;
                } catch (erro) {
                    if (tentativa >= 3 || !ehQuedaDeConexao(erro)) throw erro;

                    console.log(`  … reconectando (${relativo}: ${erro.message})`);
                    pastaAtual = null;
                    client.close();
                    await new Promise((r) => setTimeout(r, 1500 * tentativa));
                    await conectar();
                }
            }

            estadoNovo[relativo] = hashes[relativo];
            enviados++;
            console.log(`  ↑ ${relativo}`);

            // Salva o progresso de tempos em tempos: se tudo der errado de uma vez, o que já
            // subiu continua registrado.
            if (enviados % 25 === 0) salvarEstado(estadoNovo);
        }

        if (apagarRemoto) {
            for (const relativo of apagados) {
                try {
                    await client.remove(`${DIR_REMOTO}/${relativo}`);
                    console.log(`  ✖ ${relativo}`);
                } catch (e) {
                    console.log(`  ! não consegui apagar ${relativo}: ${e.message}`);
                }
                delete estadoNovo[relativo];
            }
        }
    } catch (erro) {
        salvarEstado(estadoNovo);
        abortar(`Falhou depois de ${enviados} arquivo(s): ${erro.message}\n`
              + '  O que já subiu ficou registrado — rodar de novo continua de onde parou.'
              + dicaCertificado(erro));
    } finally {
        client.close();
    }

    salvarEstado(estadoNovo);
    console.log(`\n✔ ${enviados} arquivo(s) publicados em ${FTP.host}${DIR_REMOTO}\n`);
}

main().catch((e) => abortar(e.message));

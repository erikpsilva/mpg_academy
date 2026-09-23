<?php
/**
 * Lista de presença da aula experimental — uma lista por data, separada por turma.
 *
 * Compartilhada entre o admin (/admin/presencateste, todas as turmas) e o professor
 * (/admin/prof-presenca, só as turmas dele). Quem inclui define antes:
 *
 *   $presencaTurmasPermitidas  null = todas as turmas; array de ids = só essas
 *   $presencaRota              rota da página, usada quando troca a data no seletor
 *
 * A lista de uma data não some depois de marcada: ela reúne quem estava agendado para o
 * dia, quem já foi marcado presente (realizada) e quem foi marcado como faltou. Fica de
 * fora só quem desmarcou ANTES da aula (cancelada sem presença), porque essa pessoa nem
 * era esperada.
 */
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

$DIAS  = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
$fmtDia = function (string $d) use ($DIAS): string {
    $dt = new DateTime($d);
    return $dt->format('d/m/Y') . ' (' . $DIAS[(int) $dt->format('w')] . ')';
};

// Mesmo critério de "está na lista" usado em marcar_presenca_teste.php.
$filtroLista = "(ae.status IN ('agendada','realizada') OR (ae.status = 'cancelada' AND ae.presenca = 'faltou'))";

// Professor só enxerga as próprias turmas. Os ids vêm do banco (professor_turmas), mas
// passam por intval mesmo assim antes de entrar na consulta.
$semTurmas = false;
if (is_array($presencaTurmasPermitidas)) {
    $idsTurmas = array_values(array_filter(array_map('intval', $presencaTurmasPermitidas)));
    if ($idsTurmas) {
        $filtroLista .= ' AND ae.turma_id IN (' . implode(',', $idsTurmas) . ')';
    } else {
        $semTurmas = true;
    }
}

$datas = [];
if (!$semTurmas) {
    // Todas as datas que têm lista, passadas e futuras, com o resumo de cada uma pro select.
    $datas = $pdo->query("
        SELECT DATE(ae.data_agendada) AS dia,
               COUNT(*) AS total,
               SUM(ae.status = 'agendada') AS sem_marcacao
        FROM aulas_experimentais ae
        WHERE ae.data_agendada IS NOT NULL AND $filtroLista
        GROUP BY DATE(ae.data_agendada)
        ORDER BY dia DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$diasDisponiveis = array_column($datas, 'dia');

// Data aberta: a pedida na URL; senão hoje; senão a próxima que vai acontecer; senão a
// mais recente. Assim quem abre a página no dia da aula já cai direto na chamada certa.
$dataSel = $_GET['data'] ?? '';
if (!in_array($dataSel, $diasDisponiveis, true)) {
    $dataSel = '';
    if (in_array($hoje, $diasDisponiveis, true)) {
        $dataSel = $hoje;
    } else {
        $futuras = array_filter($diasDisponiveis, fn ($d) => $d > $hoje);
        $dataSel = $futuras ? min($futuras) : ($diasDisponiveis[0] ?? '');
    }
}

$turmas = [];
if ($dataSel !== '') {
    $st = $pdo->prepare("
        SELECT ae.id, ae.status, ae.presenca, ae.turma_id,
               at.nome, at.celular, at.is_menor, at.responsavel_nome, at.responsavel_celular,
               t.nome AS turma_nome, q.nome AS quadra_nome
        FROM aulas_experimentais ae
        JOIN alunos_teste at ON at.id = ae.aluno_teste_id
        JOIN turmas t        ON t.id  = ae.turma_id
        LEFT JOIN quadras q  ON q.id  = t.quadra_id
        WHERE DATE(ae.data_agendada) = ? AND $filtroLista
        ORDER BY t.nome, at.nome
    ");
    $st->execute([$dataSel]);

    // Horário de cada turma naquele dia da semana, pra mostrar no cabeçalho do grupo.
    $stHora = $pdo->prepare("
        SELECT qh.hora_inicio, qh.hora_fim
        FROM turma_horarios th
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE th.turma_id = ? AND qh.dia_semana = ?
        ORDER BY qh.hora_inicio LIMIT 1
    ");
    $diaSemana = (int) (new DateTime($dataSel))->format('w');

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid = (int) $r['turma_id'];
        if (!isset($turmas[$tid])) {
            $stHora->execute([$tid, $diaSemana]);
            $h = $stHora->fetch(PDO::FETCH_ASSOC);
            $turmas[$tid] = [
                'nome'    => $r['turma_nome'],
                'quadra'  => $r['quadra_nome'],
                'horario' => $h ? substr($h['hora_inicio'], 0, 5) . ' às ' . substr($h['hora_fim'], 0, 5) : '',
                'alunos'  => [],
            ];
        }
        $turmas[$tid]['alunos'][] = $r;
    }
}

$ehFutura = $dataSel !== '' && $dataSel > $hoje;

/** Estado de presença de uma linha: 'presente', 'faltou' ou '' (ainda não marcado). */
function presencaEstado(array $r): string {
    if ($r['status'] === 'realizada') return 'presente';
    if ($r['status'] === 'cancelada' && $r['presenca'] === 'faltou') return 'faltou';
    return '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Lista de Presença da Aula Experimental</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminTeste presencaTeste">

            <div class="row adminTeste__pageHeader">
                <div class="col-md-8">
                    <h2>Lista de <span>Presença</span></h2>
                    <p>Aula experimental. Marque quem veio e quem faltou: <strong>Presente</strong> vai para "aula realizada" e <strong>Faltou</strong> vai para "cancelada" nos agendamentos.</p>
                </div>
                <div class="col-md-4 adminTeste__pageHeader__actions">
                    <?php if ($datas): ?>
                    <label class="presencaTeste__seletor">
                        <span>Data da lista</span>
                        <select id="presencaData" class="input">
                            <?php
                            $futuras  = array_filter($datas, fn ($d) => $d['dia'] > $hoje);
                            $passadas = array_filter($datas, fn ($d) => $d['dia'] <= $hoje);
                            foreach (['Próximas' => array_reverse($futuras), 'Hoje e anteriores' => $passadas] as $rotulo => $grupo):
                                if (!$grupo) continue; ?>
                            <optgroup label="<?= $rotulo ?>">
                                <?php foreach ($grupo as $d):
                                    $marca = $d['dia'] === $hoje ? ' · hoje' : ((int) $d['sem_marcacao'] > 0 && $d['dia'] < $hoje ? ' · ' . $d['sem_marcacao'] . ' sem marcar' : ''); ?>
                                <option value="<?= $d['dia'] ?>" <?= $d['dia'] === $dataSel ? 'selected' : '' ?>>
                                    <?= $fmtDia($d['dia']) ?> — <?= (int) $d['total'] ?> aluno<?= (int) $d['total'] === 1 ? '' : 's' ?><?= $marca ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($semTurmas): ?>
            <div class="adminTeste__empty">Você ainda não tem turmas vinculadas. Fale com a coordenação.</div>
            <?php elseif ($dataSel === ''): ?>
            <div class="adminTeste__empty">Nenhuma aula experimental agendada ainda.</div>
            <?php else: ?>

            <div class="presencaTeste__titulo">
                <h3><?= $fmtDia($dataSel) ?></h3>
                <?php if ($ehFutura): ?>
                <span class="presencaTeste__aviso">Aula ainda não aconteceu — a marcação libera no dia.</span>
                <?php endif; ?>
            </div>

            <?php foreach ($turmas as $turma):
                $tot = count($turma['alunos']);
                $pres = count(array_filter($turma['alunos'], fn ($a) => presencaEstado($a) === 'presente'));
                $falt = count(array_filter($turma['alunos'], fn ($a) => presencaEstado($a) === 'faltou')); ?>
            <div class="presencaTeste__turma" data-turma>
                <div class="presencaTeste__turmaHead">
                    <div>
                        <strong><?= htmlspecialchars($turma['nome']) ?></strong>
                        <span><?= htmlspecialchars(trim(($turma['quadra'] ?? '') . ($turma['horario'] ? ' · ' . $turma['horario'] : ''), ' ·')) ?></span>
                    </div>
                    <div class="presencaTeste__contagem" data-contagem>
                        <span class="is-presente"><b data-n="presente"><?= $pres ?></b> presente<?= $pres === 1 ? '' : 's' ?></span>
                        <span class="is-faltou"><b data-n="faltou"><?= $falt ?></b> falt<?= $falt === 1 ? 'ou' : 'aram' ?></span>
                        <span class="is-pendente"><b data-n="pendente"><?= $tot - $pres - $falt ?></b> sem marcar</span>
                    </div>
                </div>

                <?php foreach ($turma['alunos'] as $i => $a):
                    $estado  = presencaEstado($a);
                    $fone    = !empty($a['responsavel_celular']) && !empty($a['is_menor']) ? $a['responsavel_celular'] : $a['celular'];
                    $contato = !empty($a['responsavel_celular']) && !empty($a['is_menor'])
                        ? 'Resp.: ' . ($a['responsavel_nome'] ?: 'responsável')
                        : ''; ?>
                <div class="presencaTeste__linha<?= $estado ? ' is-' . $estado : '' ?>" data-id="<?= (int) $a['id'] ?>" data-estado="<?= $estado ?>">
                    <span class="presencaTeste__num"><?= $i + 1 ?></span>
                    <div class="presencaTeste__aluno">
                        <strong><?= htmlspecialchars($a['nome']) ?></strong>
                        <span>
                            <?= htmlspecialchars($fone ?: 'Sem telefone') ?>
                            <?= $contato ? ' · ' . htmlspecialchars($contato) : '' ?>
                        </span>
                    </div>
                    <div class="presencaTeste__acoes">
                        <button type="button" class="presencaTeste__btn presencaTeste__btn--presente<?= $estado === 'presente' ? ' is-ativo' : '' ?>"
                                data-marcar="presente" <?= $ehFutura ? 'disabled' : '' ?>>✓ Presente</button>
                        <button type="button" class="presencaTeste__btn presencaTeste__btn--faltou<?= $estado === 'faltou' ? ' is-ativo' : '' ?>"
                                data-marcar="faltou" <?= $ehFutura ? 'disabled' : '' ?>>✕ Faltou</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var PRESENCA_ROTA  = <?= json_encode($presencaRota) ?>;

(function () {
    var sel = document.getElementById('presencaData');
    if (sel) {
        sel.addEventListener('change', function () {
            window.location.href = ADMIN_BASE_URL + '/' + PRESENCA_ROTA + '?data=' + encodeURIComponent(sel.value);
        });
    }

    function recontar(turma) {
        var n = { presente: 0, faltou: 0, pendente: 0 };
        turma.querySelectorAll('.presencaTeste__linha').forEach(function (l) {
            n[l.dataset.estado || 'pendente']++;
        });
        Object.keys(n).forEach(function (k) {
            var el = turma.querySelector('[data-n="' + k + '"]');
            if (el) el.textContent = n[k];
        });
    }

    // Clicar no botão já marcado desfaz a marcação — é o jeito de corrigir um clique errado.
    document.querySelectorAll('[data-marcar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var linha  = btn.closest('.presencaTeste__linha');
            var alvo   = btn.dataset.marcar;
            var enviar = linha.dataset.estado === alvo ? 'limpar' : alvo;
            var botoes = linha.querySelectorAll('[data-marcar]');

            botoes.forEach(function (b) { b.disabled = true; });

            fetch(ADMIN_BASE_URL + '/services/marcar_presenca_teste.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: linha.dataset.id, presenca: enviar }).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'Não foi possível salvar.');
                var novo = d.presenca || '';
                linha.dataset.estado = novo;
                linha.classList.remove('is-presente', 'is-faltou');
                if (novo) linha.classList.add('is-' + novo);
                botoes.forEach(function (b) { b.classList.toggle('is-ativo', b.dataset.marcar === novo); });
                recontar(linha.closest('[data-turma]'));
            })
            .catch(function (e) { alert(e.message); })
            .finally(function () { botoes.forEach(function (b) { b.disabled = false; }); });
        });
    });
}());
</script>

</body>
</html>

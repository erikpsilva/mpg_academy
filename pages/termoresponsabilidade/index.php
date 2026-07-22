<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id, nome, nascimento, is_menor, responsavel_nome, responsavel_parentesco, responsavel_cpf, responsavel_celular, termo_status, termo_assinado_em, termo_assinado_nome, termo_assinado_cpf FROM alunos WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['aluno']['id']]);
$aluno = $stmt->fetch();

if (!$aluno) {
    unset($_SESSION['aluno']);
    header('Location: ' . BASE_URL);
    exit;
}

$jaAssinou = $aluno['termo_status'] === 'assinado';

$parentescoLabel = [
    'pai' => 'Pai',
    'mae' => 'Mãe',
    'responsavel_legal' => 'Responsável legal',
][$aluno['responsavel_parentesco'] ?? ''] ?? 'Responsável legal';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }

function fmtDataBr($d) {
    if (!$d) return '—';
    $dt = new DateTime($d);
    $m  = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return $dt->format('d') . ' de ' . $m[(int)$dt->format('n')-1] . ' de ' . $dt->format('Y');
}
function fmtHora($dt) {
    if (!$dt) return '—';
    return (new DateTime($dt))->format('d/m/Y \à\s H:i');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Termo de Responsabilidade</title>
<?php include ROOT . '/includes/assets.php';?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
<style>
.termoCard { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 24px; overflow: hidden; }
.termoCard__head { background: #0b0d0f; padding: 14px 24px; color: #ffd500; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.termoCard__body { padding: 24px; }
.termoInfoGrid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
.termoInfoGrid .item label { font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 3px; }
.termoInfoGrid .item span { font-size: 14px; color: #222; font-weight: 500; }
.termoTexto { font-size: 13.5px; line-height: 1.85; color: #333; }
.termoTexto h3 { font-size: 15px; font-weight: 700; color: #0b0d0f; margin: 20px 0 8px; border-bottom: 2px solid #ffd500; padding-bottom: 6px; }
.termoTexto p { margin-bottom: 12px; }
.termoStatusBar { border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 14px; }
.termoStatusBar--ok { background: #d1fae5; border: 1.5px solid #10b981; color: #065f46; }
.termoStatusBar--pendente { background: #fef3c7; border: 1.5px solid #f59e0b; color: #78350f; }
.termoSignForm { display: flex; flex-direction: column; gap: 14px; }
.termoSignForm label { font-size: 12px; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: .4px; display: block; margin-bottom: 4px; }
.termoSignForm input, .termoSignForm select { width: 100%; padding: 12px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; outline: none; }
.termoSignForm input:focus, .termoSignForm select:focus { border-color: #ffd500; }
.termoSignPreview { border: 1.5px dashed #ddd; border-radius: 8px; padding: 10px 16px; min-height: 52px; font-family: 'Great Vibes', cursive; font-size: 32px; color: #0b0d0f; background: #fafafa; }
.termoSignPreview.has-text { border-color: #ffd500; background: #fffdf0; }
.termoBtnAssinar { background: #ffd500; color: #0b0d0f; border: none; border-radius: 10px; padding: 14px 28px; font-size: 15px; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; }
.termoBtnAssinar:hover { background: #e6c000; }
.termoBtnAssinar:disabled { opacity: .5; cursor: not-allowed; }
.termoAviso { font-size: 12px; color: #888; line-height: 1.5; margin-top: 4px; }
.termoMsg { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; display: none; }
.termoMsg--ok { background: #d1fae5; color: #065f46; }
.termoMsg--err { background: #fee2e2; color: #7f1d1d; }
@media (max-width: 560px) { .termoInfoGrid { grid-template-columns: 1fr; } }
</style>
</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php';?>

<main class="studentArea">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos"><i class="icon-calendar"></i> Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados"><i class="icon-megaphone"></i> Comunicados</a>
            </nav>

            <div class="studentAreaSidebar__help">
                <h3>Precisa de ajuda?</h3>
                <p>Fale com nossa equipe pelo WhatsApp.</p>
                <a href="https://wa.me/5511972330097" target="_blank" rel="noopener">
                    <i class="icon-whatsapp"></i>
                    Falar no WhatsApp
                </a>
            </div>

            <a class="studentAreaSidebar__logout" href="<?= BASE_URL ?>/services/site/student_logout.php">
                <i class="icon-go"></i> Sair
            </a>
        </aside>

        <section class="studentAreaContent">
            <section class="studentEditDataHero">
                <div>
                    <span><i class="icon-user" aria-hidden="true"></i></span>
                    <h1>Termo de Responsabilidade</h1>
                    <p>Autorização e responsabilidade dos pais/responsável para menores de idade matriculados na MPG Academy.</p>
                </div>
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-go" aria-hidden="true"></i> Voltar ao dashboard</a>
            </section>

            <?php if (!$aluno['is_menor']): ?>

            <div class="termoStatusBar termoStatusBar--ok">✅ Este termo não se aplica a este cadastro (aluno maior de idade).</div>

            <?php else: ?>

            <?php if ($jaAssinou): ?>
            <div class="termoStatusBar termoStatusBar--ok">✅ Termo assinado por <?= e($aluno['termo_assinado_nome']) ?> em <?= fmtHora($aluno['termo_assinado_em']) ?>.</div>
            <?php else: ?>
            <div class="termoStatusBar termoStatusBar--pendente">🕐 Aguardando a assinatura do responsável. Leia o termo completo abaixo.</div>
            <?php endif; ?>

            <div class="termoCard">
                <div class="termoCard__head">Dados do Aluno e do Responsável</div>
                <div class="termoCard__body">
                    <div class="termoInfoGrid">
                        <div class="item"><label>Aluno</label><span><?= e($aluno['nome']) ?></span></div>
                        <div class="item"><label>Data de nascimento</label><span><?= $aluno['nascimento'] ? fmtDataBr($aluno['nascimento']) : '—' ?></span></div>
                        <div class="item"><label>Responsável</label><span><?= e($aluno['responsavel_nome']) ?></span></div>
                        <div class="item"><label>Parentesco</label><span><?= e($parentescoLabel) ?></span></div>
                    </div>
                </div>
            </div>

            <div class="termoCard">
                <div class="termoCard__head">Termo de Autorização e Responsabilidade – Menor de Idade</div>
                <div class="termoCard__body">
                    <div class="termoTexto">
                        <p>Eu, responsável legal pelo(a) menor <strong><?= e($aluno['nome']) ?></strong>, declaro para os devidos fins que autorizo a participação do(a) referido(a) menor nas atividades esportivas de voleibol oferecidas pela <strong>MPG Academy</strong>, conforme as cláusulas e condições estabelecidas a seguir.</p>

                        <h3>Cláusula 1 – Da Autorização</h3>
                        <p>Autorizo o(a) menor a participar das aulas e demais atividades regulares de voleibol promovidas pela <strong>MPG Academy</strong>, ciente de que se tratam de atividades físicas com movimentos esportivos que exigem condicionamento físico adequado.</p>

                        <h3>Cláusula 2 – Da Aptidão Física</h3>
                        <p>Declaro que o(a) menor se encontra em boas condições de saúde e apto(a) à prática de atividades físicas esportivas, não apresentando nenhuma contraindicação médica que impeça sua participação. Responsabilizo-me por comunicar à MPG Academy qualquer alteração em seu estado de saúde.</p>

                        <h3>Cláusula 3 – Da Responsabilidade</h3>
                        <p>Assumo plena responsabilidade por quaisquer eventos decorrentes da participação do(a) menor nas atividades, isentando a MPG Academy, seus professores, funcionários e parceiros de responsabilidade civil por acidentes ou imprevistos que não decorram de negligência comprovada da escola.</p>

                        <h3>Cláusula 4 – Uso de Imagem</h3>
                        <p>Autorizo o uso da imagem e voz do(a) menor em fotos e vídeos produzidos pela MPG Academy para fins institucionais, redes sociais e divulgação das atividades, sem fins lucrativos diretos ao menor.</p>

                        <h3>Cláusula 5 – Da Validade Eletrônica</h3>
                        <p>As partes reconhecem que a assinatura eletrônica deste documento possui plena validade jurídica, nos termos da <strong>Lei nº 14.063/2020</strong> e do <strong>Marco Civil da Internet (Lei nº 12.965/2014)</strong>, sendo gerado registro de identidade, data, hora e endereço IP de cada assinante.</p>

                        <p style="margin-top:20px;color:#888;font-size:12px;">São Paulo, <?= fmtDataBr(date('Y-m-d')) ?></p>
                    </div>
                </div>
            </div>

            <?php if ($jaAssinou): ?>
            <div class="termoCard">
                <div class="termoCard__head">Assinatura</div>
                <div class="termoCard__body">
                    <div class="termoInfoGrid">
                        <div class="item"><label>Assinado por</label><span><?= e($aluno['termo_assinado_nome']) ?></span></div>
                        <div class="item"><label>CPF</label><span><?= substr($aluno['termo_assinado_cpf'], 0, 3) . '.***.***-' . substr($aluno['termo_assinado_cpf'], -2) ?></span></div>
                        <div class="item"><label>Data e hora</label><span><?= fmtHora($aluno['termo_assinado_em']) ?></span></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="termoCard">
                <div class="termoCard__head">✍ Assinar como Responsável</div>
                <div class="termoCard__body">
                    <form class="termoSignForm" id="termoSignForm">
                        <div>
                            <label>Nome completo do responsável <span style="color:#e00">*</span></label>
                            <input type="text" id="termoSignNome" value="<?= e($aluno['responsavel_nome']) ?>" required>
                            <div class="termoSignPreview has-text" id="termoSignPreview"><?= e($aluno['responsavel_nome']) ?></div>
                        </div>
                        <div>
                            <label>CPF <span style="color:#e00">*</span></label>
                            <input type="text" id="termoSignCpf" value="<?= e($aluno['responsavel_cpf']) ?>" maxlength="14" required>
                        </div>
                        <p class="termoAviso">Ao clicar em "Assinar", você confirma que leu e concorda com todos os termos acima. Será gerado um registro com seu nome, CPF, data, hora e IP para fins de comprovação legal.</p>
                        <div class="termoMsg" id="termoMsg"></div>
                        <button type="submit" class="termoBtnAssinar" id="termoSignBtn">✍ Assinar o Termo</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </section>
    </div>
</main>

<?php include ROOT . '/includes/scripts.php';?>
<script>
(function () {
    var nomeInput = document.getElementById('termoSignNome');
    var preview   = document.getElementById('termoSignPreview');
    if (nomeInput) {
        nomeInput.addEventListener('input', function () {
            var v = this.value.trim();
            preview.textContent = v;
            preview.classList.toggle('has-text', v.length > 0);
        });
    }

    var cpfInput = document.getElementById('termoSignCpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function () {
            var v = this.value.replace(/\D/g, '').slice(0, 11);
            if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d+)/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/^(\d{3})(\d+)/, '$1.$2');
            this.value = v;
        });
    }

    var form = document.getElementById('termoSignForm');
    var msg  = document.getElementById('termoMsg');
    var btn  = document.getElementById('termoSignBtn');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var nome = nomeInput.value.trim();
            var cpf  = cpfInput.value.replace(/\D/g, '');
            if (!nome || cpf.length < 11) {
                showMsg('Preencha nome e CPF completo.', false);
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Assinando...';

            var fd = new FormData();
            fd.append('nome', nome);
            fd.append('cpf', cpf);

            fetch('<?= BASE_URL ?>/services/site/assinar_termo_menor.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        showMsg('✅ Assinatura registrada com sucesso! A página será atualizada.', true);
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        btn.disabled = false;
                        btn.textContent = '✍ Assinar o Termo';
                        showMsg(res.message || 'Erro ao assinar.', false);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = '✍ Assinar o Termo';
                    showMsg('Erro de conexão. Tente novamente.', false);
                });
        });
    }

    function showMsg(text, ok) {
        msg.textContent = text;
        msg.className = 'termoMsg ' + (ok ? 'termoMsg--ok' : 'termoMsg--err');
        msg.style.display = 'block';
    }
})();
</script>

</body>
</html>

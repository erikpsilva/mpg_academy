<?php
if (empty($_SESSION['aluno'])) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/mercadopago.php';

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM alunos WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['aluno']['id']]);
$aluno = $stmt->fetch();

if (!$aluno) {
    unset($_SESSION['aluno']);
    header('Location: ' . BASE_URL);
    exit;
}

// Salva em $perfil — header.php sobrescreve $aluno com dados da sessão
$perfil = array_merge([
    'nome' => '', 'email' => '', 'cpf' => '', 'nascimento' => '',
    'sexo' => '', 'celular' => '', 'whatsapp' => '',
    'cep' => '', 'rua' => '', 'numero' => '', 'bairro' => '',
    'complemento' => '', 'cidade' => '', 'estado' => '',
    'foto' => null, 'origem' => '',
], $aluno);

$nomePartes   = explode(' ', $perfil['nome'], 2);
$primeiroNome = $nomePartes[0];
$sobrenome    = $nomePartes[1] ?? '';
$nascimento   = !empty($perfil['nascimento']) ? date('d/m/Y', strtotime($perfil['nascimento'])) : '';
$cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $perfil['cpf']);

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$mpPublicKey = mpPublicKey($pdo);

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
function sel($opt, $val) { return $opt === $val ? ' selected' : ''; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Meu Perfil</title>
<?php include ROOT . '/includes/assets.php';?>
</head>

<body>

<?php $isStudentArea = true; ?>
<?php include ROOT . '/includes/header/header.php';?>

<main class="studentArea studentEditData">
    <div class="studentArea__layout">
        <aside class="studentAreaSidebar">
            <nav class="studentAreaSidebar__nav" aria-label="Menu do aluno">
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-home"></i> Dashboard</a>

                <strong>Geral</strong>
                <a href="<?= BASE_URL ?>/meuperfil" class="is-active"><i class="icon-user"></i> Meu Perfil</a>
                <a href="<?= BASE_URL ?>/mensalidades"><i class="icon-creditcard"></i> Mensalidades</a>
                <a href="<?= BASE_URL ?>/treinos"><i class="icon-calendar"></i> Agenda</a>
                <a href="<?= BASE_URL ?>/comunicados"><i class="icon-megaphone"></i> Comunicados</a>

                <strong>Extras</strong>
                <a href="#indique"><i class="icon-comunidade"></i> Indique um amigo</a>
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
                    <h1>Meu perfil</h1>
                    <p>Atualize suas informações de contato, endereço, acesso e foto do perfil.</p>
                </div>
                <a href="<?= BASE_URL ?>/areadoaluno"><i class="icon-go" aria-hidden="true"></i> Voltar ao dashboard</a>
            </section>

            <form class="studentSignupForm studentEditDataForm" id="studentEditDataForm"
                  action="<?= BASE_URL ?>/services/site/update_student.php"
                  method="post" enctype="multipart/form-data" novalidate>

                <section class="studentSignupBox studentSignupBox--full">
                    <div class="studentEditDataForm__profile">
                        <label class="studentSignup__photo" for="studentPhoto">
                            <input type="file" name="foto" id="studentPhoto" accept="image/*">
                            <span class="studentSignup__photoPreview">
                                <?php if (!empty($perfil['foto'])) : ?>
                                    <img src="<?= BASE_URL ?>/<?= e($perfil['foto']) ?>" alt="<?= e($perfil['nome']) ?>">
                                <?php else : ?>
                                    <i class="icon-user" aria-hidden="true"></i>
                                <?php endif; ?>
                            </span>
                            <span class="studentSignup__photoAdd" aria-hidden="true">+</span>
                        </label>
                        <div>
                            <h2><i class="icon-user" aria-hidden="true"></i> Dados pessoais</h2>
                            <p>Nome, CPF e data de nascimento ficam bloqueados por segurança. Para corrigir esses dados, fale com a equipe MPG.</p>
                        </div>
                    </div>

                    <div class="studentSignupGrid studentSignupGrid--full studentEditDataForm__locked">
                        <label class="studentField is-disabled">
                            <span class="studentField__icon" aria-hidden="true"><i class="icon-user"></i></span>
                            <span class="studentField__label">Nome</span>
                            <input type="text" value="<?= e($primeiroNome) ?>" disabled>
                        </label>

                        <label class="studentField is-disabled">
                            <span class="studentField__icon" aria-hidden="true"><i class="icon-user"></i></span>
                            <span class="studentField__label">Sobrenome</span>
                            <input type="text" value="<?= e($sobrenome) ?>" disabled>
                        </label>

                        <label class="studentField is-disabled">
                            <span class="studentField__icon" aria-hidden="true"><i class="icon-cpf"></i></span>
                            <span class="studentField__label">CPF</span>
                            <input type="text" value="<?= e($cpfFormatado) ?>" disabled>
                        </label>

                        <label class="studentField is-disabled">
                            <span class="studentField__icon" aria-hidden="true"><i class="icon-calendar"></i></span>
                            <span class="studentField__label">Data de nascimento</span>
                            <input type="text" value="<?= e($nascimento) ?>" disabled>
                        </label>

                        <label class="studentField">
                            <span class="studentField__icon" aria-hidden="true"><i class="icon-user"></i></span>
                            <span class="studentField__label">Sexo <b>*</b></span>
                            <select name="sexo" required>
                                <option value="">Selecione</option>
                                <option value="feminino"<?= sel($perfil['sexo'], 'feminino') ?>>Feminino</option>
                                <option value="masculino"<?= sel($perfil['sexo'], 'masculino') ?>>Masculino</option>
                                <option value="outro"<?= sel($perfil['sexo'], 'outro') ?>>Outro</option>
                            </select>
                        </label>
                    </div>
                </section>

                <div class="studentSignupForm__columns">
                    <section class="studentSignupBox">
                        <h2><i class="icon-celphone" aria-hidden="true"></i> Contato</h2>

                        <div class="studentSignupGrid studentSignupGrid--two">
                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-celphone"></i></span>
                                <span class="studentField__label">Celular (com DDD) <b>*</b></span>
                                <input type="text" name="celular" class="studentPhone" value="<?= e($perfil['celular']) ?>" required>
                            </label>

                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-mail"></i></span>
                                <span class="studentField__label">E-mail <b>*</b></span>
                                <input type="email" name="email" value="<?= e($perfil['email']) ?>" required>
                            </label>

                        </div>
                    </section>

                    <section class="studentSignupBox">
                        <h2><i class="icon-zonanorte" aria-hidden="true"></i> Endereço</h2>

                        <div class="studentSignupGrid studentSignupGrid--address">
                            <label class="studentField studentField--cep">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-search"></i></span>
                                <span class="studentField__label">CEP <b>*</b></span>
                                <input type="text" name="cep" id="studentCep" value="<?= e($perfil['cep']) ?>" required>
                                <button type="button" id="studentSearchCep">Buscar CEP</button>
                            </label>

                            <label class="studentField studentField--wide">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-rua"></i></span>
                                <span class="studentField__label">Rua <b>*</b></span>
                                <input type="text" name="rua" value="<?= e($perfil['rua']) ?>" required>
                            </label>

                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-numero"></i></span>
                                <span class="studentField__label">Número <b>*</b></span>
                                <input type="text" name="numero" value="<?= e($perfil['numero']) ?>" required>
                            </label>

                            <label class="studentField studentField--wide">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-bairro"></i></span>
                                <span class="studentField__label">Bairro <b>*</b></span>
                                <input type="text" name="bairro" value="<?= e($perfil['bairro']) ?>" required>
                            </label>

                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-complemento"></i></span>
                                <span class="studentField__label">Complemento</span>
                                <input type="text" name="complemento" value="<?= e($perfil['complemento'] ?? '') ?>">
                            </label>

                            <label class="studentField studentField--wide">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-cidade"></i></span>
                                <span class="studentField__label">Cidade <b>*</b></span>
                                <input type="text" name="cidade" value="<?= e($perfil['cidade']) ?>" required>
                            </label>

                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-estado"></i></span>
                                <span class="studentField__label">Estado <b>*</b></span>
                                <select name="estado" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($estados as $uf) : ?>
                                        <option value="<?= $uf ?>"<?= sel($perfil['estado'], $uf) ?>><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="studentSignupBox studentSignupBox--full" id="autoPagamentoBox">
                        <h2><i class="icon-creditcard" aria-hidden="true"></i> Pagamento automático</h2>
                        <p style="color:#aaa;font-size:13px;margin-bottom:16px;">
                            Ative para que suas mensalidades sejam cobradas automaticamente no cartão, todo mês, sem precisar pagar manualmente.
                        </p>

                        <?php if (!empty($perfil['cartao_final4'])): ?>
                        <div class="autoPagCard" id="autoPagCardInfo">
                            <div class="autoPagCard__info">
                                <i class="icon-creditcard" aria-hidden="true"></i>
                                <span><?= e(ucfirst($perfil['cartao_bandeira'] ?? '')) ?> final <?= e($perfil['cartao_final4']) ?></span>
                            </div>
                            <label class="autoPagCard__switch">
                                <input type="checkbox" id="chkAutoPagamento" <?= !empty($perfil['auto_pagamento']) ? 'checked' : '' ?>>
                                <span>Cobrança automática ativada</span>
                            </label>
                            <button type="button" id="btnTrocarCartao" class="autoPagCard__link">Trocar cartão</button>
                            <button type="button" id="btnRemoverCartao" class="autoPagCard__link autoPagCard__link--danger">Remover cartão</button>
                        </div>
                        <?php else: ?>
                        <div id="autoPagCardInfo" style="display:none;"></div>
                        <?php endif; ?>

                        <div id="autoPagForm" style="<?= !empty($perfil['cartao_final4']) ? 'display:none;' : '' ?>">
                            <div id="autoPagBrick_container"></div>
                        </div>
                    </section>

                    <section class="studentSignupBox">
                        <h2><i class="icon-padlock" aria-hidden="true"></i> Acesso</h2>

                        <div class="studentSignupGrid studentSignupGrid--two">
                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-padlock"></i></span>
                                <span class="studentField__label">Nova senha</span>
                                <input type="password" name="senha" class="studentPassword" placeholder="Preencha apenas se quiser alterar">
                                <button class="studentField__toggle" type="button" aria-label="Mostrar senha"><i class="icon-ver" aria-hidden="true"></i></button>
                            </label>

                            <label class="studentField">
                                <span class="studentField__icon" aria-hidden="true"><i class="icon-padlock"></i></span>
                                <span class="studentField__label">Confirmar nova senha</span>
                                <input type="password" name="confirmar_senha" class="studentPassword" placeholder="Repita a nova senha">
                                <button class="studentField__toggle" type="button" aria-label="Mostrar senha"><i class="icon-ver" aria-hidden="true"></i></button>
                            </label>
                        </div>
                    </section>
                </div>

                <section class="studentEditDataActions">
                    <div>
                        <h2>Salvar alterações</h2>
                        <p>Confira os dados antes de salvar. As mudanças serão aplicadas ao seu perfil de aluno.</p>
                    </div>
                    <a href="<?= BASE_URL ?>/areadoaluno">Cancelar</a>
                    <button type="submit">Salvar dados <i class="icon-go" aria-hidden="true"></i></button>
                </section>
            </form>
        </section>
    </div>
</main>

<?php include ROOT . '/includes/scripts.php';?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
var BASE_URL      = "<?= BASE_URL ?>";
var MP_PUBLIC_KEY = "<?= $mpPublicKey ?>";
var ALUNO_EMAIL   = "<?= htmlspecialchars($perfil['email'] ?? '', ENT_QUOTES) ?>";
</script>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/meuperfil/meuperfil.js?' . $version . '"></script>';
?>

</body>
</html>

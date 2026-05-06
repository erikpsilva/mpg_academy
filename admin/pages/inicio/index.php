<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

// Totais por nível
$stmt  = $pdo->query("SELECT nivel_acesso, COUNT(*) as total FROM admin_usuarios GROUP BY nivel_acesso");
$niveis = ['admin' => 0, 'editor' => 0, 'leitor' => 0];
$totalUsuarios = 0;
foreach ($stmt->fetchAll() as $row) {
    $niveis[$row['nivel_acesso']] = $row['total'];
    $totalUsuarios += $row['total'];
}

// Últimos 5 usuários cadastrados
$recentes = $pdo->query("SELECT nome_completo, email, nivel_acesso, created_at FROM admin_usuarios ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<title>MPG Academy - Admin - Início</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="adminInicio">

            <!-- Boas-vindas -->
            <div class="row adminInicio__header">
                <div class="col-md-12">
                    <h2>Olá, <span><?= htmlspecialchars(explode(' ', $_SESSION['usuario']['nome_completo'])[0]) ?></span>!</h2>
                    <p>Aqui está um resumo do sistema.</p>
                </div>
            </div>

            <!-- Cards de estatísticas -->
            <div class="row adminInicio__stats">
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--total">
                        <span class="dashCard__number"><?= $totalUsuarios ?></span>
                        <span class="dashCard__label">Total de Usuários</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--admin">
                        <span class="dashCard__number"><?= $niveis['admin'] ?></span>
                        <span class="dashCard__label">Admins</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--editor">
                        <span class="dashCard__number"><?= $niveis['editor'] ?></span>
                        <span class="dashCard__label">Editores</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="dashCard dashCard--leitor">
                        <span class="dashCard__number"><?= $niveis['leitor'] ?></span>
                        <span class="dashCard__label">Leitores</span>
                    </div>
                </div>
            </div>

            <!-- Perfil + Últimos usuários -->
            <div class="row">

                <!-- Card do usuário logado -->
                <div class="col-md-4">
                    <div class="dashPerfil">
                        <h4 class="dashPerfil__title">Meu Perfil</h4>
                        <ul class="dashPerfil__list">
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Nome</span>
                                <span class="dashPerfil__val"><?= htmlspecialchars($_SESSION['usuario']['nome_completo']) ?></span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">E-mail</span>
                                <span class="dashPerfil__val"><?= htmlspecialchars($_SESSION['usuario']['email']) ?></span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Nível</span>
                                <span class="dashPerfil__val">
                                    <span class="nivelBadge nivelBadge--<?= $_SESSION['usuario']['nivel_acesso'] ?>">
                                        <?= strtoupper($_SESSION['usuario']['nivel_acesso']) ?>
                                    </span>
                                </span>
                            </li>
                            <li class="dashPerfil__item">
                                <span class="dashPerfil__key">Acesso em</span>
                                <span class="dashPerfil__val"><?= date('d/m/Y H:i') ?></span>
                            </li>
                        </ul>
                        <a href="<?= BASE_URL ?>/admin/meusdados" class="btn btn--primary dashPerfil__btn">Editar perfil</a>
                    </div>
                </div>

                <!-- Últimos usuários -->
                <div class="col-md-8">
                    <div class="dashRecentes">
                        <h4 class="dashRecentes__title">Últimos usuários cadastrados</h4>
                        <table class="dashTable">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Nível</th>
                                    <th>Cadastrado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentes as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['nome_completo']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <span class="nivelBadge nivelBadge--<?= $u['nivel_acesso'] ?>">
                                            <?= strtoupper($u['nivel_acesso']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

</body>
</html>

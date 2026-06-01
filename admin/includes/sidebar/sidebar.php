<aside class="sidebar">
    <nav class="sidebar__nav">
        <ul class="sidebar__menu">
            <li class="sidebar__section">Plataforma</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/inicio"
                   class="sidebar__link <?= ($subRoute === 'inicio') ? 'sidebar__link--active' : '' ?>">
                    Início
                </a>
            </li>

            <?php if ($_SESSION['usuario']['nivel_acesso'] === 'admin'): ?>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/cadastrarusuario"
                   class="sidebar__link <?= ($subRoute === 'cadastrarusuario') ? 'sidebar__link--active' : '' ?>">
                    Cadastrar Usuário
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/meusdados"
                   class="sidebar__link <?= ($subRoute === 'meusdados') ? 'sidebar__link--active' : '' ?>">
                    Meus Dados
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/usuarios"
                   class="sidebar__link <?= ($subRoute === 'usuarios') ? 'sidebar__link--active' : '' ?>">
                    Administrar Usuários
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/configuracoes"
                   class="sidebar__link <?= ($subRoute === 'configuracoes') ? 'sidebar__link--active' : '' ?>">
                    Configurações
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Alunos MPG</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/alunos"
                   class="sidebar__link <?= ($subRoute === 'alunos') ? 'sidebar__link--active' : '' ?>">
                    Ver Alunos
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/interessados"
                   class="sidebar__link <?= ($subRoute === 'interessados') ? 'sidebar__link--active' : '' ?>">
                    Consultar Interessados
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/filadeespera"
                   class="sidebar__link <?= ($subRoute === 'filadeespera') ? 'sidebar__link--active' : '' ?>">
                    Fila de Espera
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/alunosteste"
                   class="sidebar__link <?= ($subRoute === 'alunosteste') ? 'sidebar__link--active' : '' ?>">
                    Alunos Teste
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/comunicados"
                   class="sidebar__link <?= ($subRoute === 'comunicados') ? 'sidebar__link--active' : '' ?>">
                    Avisos
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Financeiro</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/financeiro"
                   class="sidebar__link <?= ($subRoute === 'financeiro') ? 'sidebar__link--active' : '' ?>">
                    Dashboard
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/financeiro?aba=dividas"
                   class="sidebar__link">
                    Dívidas
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Administrativo</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/quadras"
                   class="sidebar__link <?= in_array($subRoute, ['quadras','cadastrarquadra']) ? 'sidebar__link--active' : '' ?>">
                    Quadras
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/turmas"
                   class="sidebar__link <?= ($subRoute === 'turmas') ? 'sidebar__link--active' : '' ?>">
                    Turmas
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/professores"
                   class="sidebar__link <?= ($subRoute === 'professores') ? 'sidebar__link--active' : '' ?>">
                    Professores
                </a>
            </li>

        </ul>
    </nav>
</aside>

<div class="sidebar__overlay" id="sidebarOverlay"></div>

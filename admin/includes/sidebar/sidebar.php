<aside class="sidebar">
    <button class="sidebar__close" id="closeSidebar" aria-label="Fechar menu">&#10005;</button>
    <nav class="sidebar__nav">
        <ul class="sidebar__menu">

        <?php if (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'professor'): ?>
            <!-- Menu exclusivo do professor -->
            <li class="sidebar__section">Minha Área</li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/area-professor"
                   class="sidebar__link <?= ($subRoute === 'area-professor') ? 'sidebar__link--active' : '' ?>">
                    Início
                </a>
            </li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/prof-turmas"
                   class="sidebar__link <?= ($subRoute === 'prof-turmas') ? 'sidebar__link--active' : '' ?>">
                    Turmas
                </a>
            </li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/meus-pagamentos"
                   class="sidebar__link <?= ($subRoute === 'meus-pagamentos') ? 'sidebar__link--active' : '' ?>">
                    Pagamentos
                </a>
            </li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/minhas-aulas"
                   class="sidebar__link <?= ($subRoute === 'minhas-aulas') ? 'sidebar__link--active' : '' ?>">
                    Aulas
                </a>
            </li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/minha-frequencia"
                   class="sidebar__link <?= ($subRoute === 'minha-frequencia') ? 'sidebar__link--active' : '' ?>">
                    Frequência
                </a>
            </li>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/meu-contrato"
                   class="sidebar__link <?= ($subRoute === 'meu-contrato') ? 'sidebar__link--active' : '' ?>">
                    Contrato
                </a>
            </li>
        <?php elseif (($_SESSION['usuario']['nivel_acesso'] ?? '') === 'batebola'): ?>
            <!-- Menu exclusivo do perfil Bate Bola -->
            <li class="sidebar__section">Bate Bola</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebola"
                   class="sidebar__link <?= ($subRoute === 'batebola') ? 'sidebar__link--active' : '' ?>">
                    Jogadores
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolapresenca"
                   class="sidebar__link <?= ($subRoute === 'batebolapresenca') ? 'sidebar__link--active' : '' ?>">
                    Lista de Presença
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolatimes"
                   class="sidebar__link <?= ($subRoute === 'batebolatimes') ? 'sidebar__link--active' : '' ?>">
                    Sortear Times
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolahistorico"
                   class="sidebar__link <?= ($subRoute === 'batebolahistorico') ? 'sidebar__link--active' : '' ?>">
                    Histórico
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolaconfiguracoes"
                   class="sidebar__link <?= ($subRoute === 'batebolaconfiguracoes') ? 'sidebar__link--active' : '' ?>">
                    Configurações
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Conta</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/meusdados"
                   class="sidebar__link <?= ($subRoute === 'meusdados') ? 'sidebar__link--active' : '' ?>">
                    Meus Dados
                </a>
            </li>
        <?php else: ?>
            <li class="sidebar__section">Home</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/inicio"
                   class="sidebar__link <?= ($subRoute === 'inicio') ? 'sidebar__link--active' : '' ?>">
                    Início
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Financeiro</li>

            <?php $abaFinanceiro = $_GET['aba'] ?? ''; ?>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/financeiro"
                   class="sidebar__link <?= ($subRoute === 'financeiro' && $abaFinanceiro === '') ? 'sidebar__link--active' : '' ?>">
                    Dashboard
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/financeiro?aba=dividas"
                   class="sidebar__link <?= ($subRoute === 'financeiro' && $abaFinanceiro === 'dividas') ? 'sidebar__link--active' : '' ?>">
                    Dívidas
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/caixa"
                   class="sidebar__link <?= ($subRoute === 'caixa') ? 'sidebar__link--active' : '' ?>">
                    Caixa
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/patrocinadores"
                   class="sidebar__link <?= ($subRoute === 'patrocinadores') ? 'sidebar__link--active' : '' ?>">
                    Patrocinadores
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
                <a href="<?= BASE_URL ?>/admin/mensalidades"
                   class="sidebar__link <?= ($subRoute === 'mensalidades') ? 'sidebar__link--active' : '' ?>">
                    Mensalidades
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
                <a href="<?= BASE_URL ?>/admin/todosalunos-teste"
                   class="sidebar__link <?= ($subRoute === 'todosalunos-teste') ? 'sidebar__link--active' : '' ?>">
                    Todos Alunos Teste
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/testescancelados"
                   class="sidebar__link <?= ($subRoute === 'testescancelados') ? 'sidebar__link--active' : '' ?>">
                    Testes Cancelados
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/comunicados"
                   class="sidebar__link <?= ($subRoute === 'comunicados') ? 'sidebar__link--active' : '' ?>">
                    Avisos
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/emailcadastro"
                   class="sidebar__link <?= ($subRoute === 'emailcadastro') ? 'sidebar__link--active' : '' ?>">
                    Enviar Email de Cadastro
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/uniformes"
                   class="sidebar__link <?= ($subRoute === 'uniformes') ? 'sidebar__link--active' : '' ?>">
                    Uniformes
                </a>
            </li>

            <?php if (in_array($_SESSION['usuario']['nivel_acesso'] ?? '', ['admin', 'editor'], true)): ?>
            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/pedirfuniforme"
                   class="sidebar__link <?= ($subRoute === 'pedirfuniforme') ? 'sidebar__link--active' : '' ?>">
                    Pedir Uniforme
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/pagamentos-uniformes"
                   class="sidebar__link <?= ($subRoute === 'pagamentos-uniformes') ? 'sidebar__link--active' : '' ?>">
                    Pagamentos Uniformes
                </a>
            </li>
            <?php endif; ?>

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

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/historico-aulas"
                   class="sidebar__link <?= ($subRoute === 'historico-aulas') ? 'sidebar__link--active' : '' ?>">
                    Histórico de Aulas
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/aulas-canceladas"
                   class="sidebar__link <?= ($subRoute === 'aulas-canceladas') ? 'sidebar__link--active' : '' ?>">
                    Aulas Canceladas
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Bate Bola</li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebola"
                   class="sidebar__link <?= ($subRoute === 'batebola') ? 'sidebar__link--active' : '' ?>">
                    Jogadores
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolapresenca"
                   class="sidebar__link <?= ($subRoute === 'batebolapresenca') ? 'sidebar__link--active' : '' ?>">
                    Lista de Presença
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolatimes"
                   class="sidebar__link <?= ($subRoute === 'batebolatimes') ? 'sidebar__link--active' : '' ?>">
                    Sortear Times
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolahistorico"
                   class="sidebar__link <?= ($subRoute === 'batebolahistorico') ? 'sidebar__link--active' : '' ?>">
                    Histórico
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/batebolaconfiguracoes"
                   class="sidebar__link <?= ($subRoute === 'batebolaconfiguracoes') ? 'sidebar__link--active' : '' ?>">
                    Configurações
                </a>
            </li>

            <li class="sidebar__divider" aria-hidden="true"></li>
            <li class="sidebar__section">Plataforma</li>

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
                <a href="<?= BASE_URL ?>/admin/erros-pagamento"
                   class="sidebar__link <?= ($subRoute === 'erros-pagamento') ? 'sidebar__link--active' : '' ?>">
                    Erros de Pagamento
                </a>
            </li>

            <li class="sidebar__item">
                <a href="<?= BASE_URL ?>/admin/configuracoes"
                   class="sidebar__link <?= ($subRoute === 'configuracoes') ? 'sidebar__link--active' : '' ?>">
                    Configurações
                </a>
            </li>

        <?php endif; // fim do bloco professor vs admin ?>
        </ul>
    </nav>
</aside>

<div class="sidebar__overlay" id="sidebarOverlay"></div>

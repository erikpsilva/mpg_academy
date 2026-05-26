<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy | Cadastro do Aluno</title>

<?php include ROOT . '/includes/assets.php';?>

</head>

<body data-base-url="<?= BASE_URL ?>">

<main class="studentSignup">
    <header class="studentSignup__header">
        <a class="studentSignup__brand" href="<?= BASE_URL ?>" aria-label="MPG Academy">
            <img src="<?= BASE_URL ?>/images/logo.png" alt="MPG Academy">
        </a>

        <a class="studentSignup__help" href="https://wa.me/5511972330097" target="_blank" rel="noopener">
            <i class="icon-whatsapp" aria-hidden="true"></i>
            <span>
                <strong>Dúvidas?</strong>
                Fale no WhatsApp
            </span>
            <i class="icon-go" aria-hidden="true"></i>
        </a>
    </header>

    <section class="studentSignup__hero">
        <label class="studentSignup__photo" for="studentPhoto">
            <input type="file" name="foto" id="studentPhoto" accept="image/*">
            <span class="studentSignup__photoPreview">
                <i class="icon-areadoaluno" aria-hidden="true"></i>
            </span>
            <span class="studentSignup__photoAdd" aria-hidden="true">+</span>
        </label>
        <h1>Cadastro do aluno</h1>
        <p>Preencha seus dados para criar sua conta na MPG Academy.</p>
    </section>

    <form class="studentSignupForm" id="studentSignupForm" action="<?= BASE_URL ?>/services/site/register_student.php" data-redirect="<?= BASE_URL ?>" method="post" enctype="multipart/form-data" novalidate>
        <section class="studentSignupBox studentSignupBox--full">
            <h2><i class="icon-areadoaluno" aria-hidden="true"></i> Dados pessoais</h2>

            <div class="studentSignupGrid studentSignupGrid--full">
                <label class="studentField studentField--full">
                    <span class="studentField__icon" aria-hidden="true"><i class="icon-user"></i></span>
                    <span class="studentField__label">Nome completo <b>*</b></span>
                    <input type="text" name="nome" placeholder="Digite seu nome completo" required>
                </label>

                <label class="studentField">
                    <span class="studentField__icon" aria-hidden="true"><i class="icon-cpf"></i></span>
                    <span class="studentField__label">CPF <b>*</b></span>
                    <input type="text" name="cpf" id="studentCpf" placeholder="000.000.000-00" required>
                </label>

                <label class="studentField">
                    <span class="studentField__icon" aria-hidden="true"><i class="icon-calendar"></i></span>
                    <span class="studentField__label">Data de nascimento <b>*</b></span>
                    <input type="text" name="nascimento" id="studentBirthDate" placeholder="DD/MM/AAAA" required>
                </label>

                <label class="studentField">
                    <span class="studentField__icon" aria-hidden="true"><i class="icon-user"></i></span>
                    <span class="studentField__label">Sexo <b>*</b></span>
                    <select name="sexo" required>
                        <option value="">Selecione</option>
                        <option value="feminino">Feminino</option>
                        <option value="masculino">Masculino</option>
                        <option value="outro">Outro</option>
                    </select>
                </label>
            </div>
        </section>

        <div class="studentSignupForm__columns">
            <section class="studentSignupBox">
                <h2><i class="icon-contact" aria-hidden="true"></i> Contato</h2>

                <div class="studentSignupGrid studentSignupGrid--two">
                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-celphone"></i></span>
                        <span class="studentField__label">Celular (com DDD) <b>*</b></span>
                        <input type="text" name="celular" class="studentPhone" placeholder="(11) 99999-9999" required>
                    </label>

                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-mail"></i></span>
                        <span class="studentField__label">E-mail <b>*</b></span>
                        <input type="email" name="email" placeholder="seuemail@exemplo.com" required>
                    </label>

                    <label class="studentField studentField--full">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-whatsapp"></i></span>
                        <span class="studentField__label">WhatsApp (mesmo número) <b>*</b></span>
                        <input type="text" name="whatsapp" class="studentPhone" placeholder="(11) 99999-9999" required>
                    </label>
                </div>
            </section>

            <section class="studentSignupBox">
                <h2><i class="icon-zonanorte" aria-hidden="true"></i> Endereço</h2>

                <div class="studentSignupGrid studentSignupGrid--address">
                    <label class="studentField studentField--cep">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-search"></i></span>
                        <span class="studentField__label">CEP <b>*</b></span>
                        <input type="text" name="cep" id="studentCep" placeholder="00000-000" required>
                        <button type="button" id="studentSearchCep">Buscar CEP</button>
                    </label>

                    <label class="studentField studentField--wide">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-rua"></i></span>
                        <span class="studentField__label">Rua <b>*</b></span>
                        <input type="text" name="rua" placeholder="Nome da rua" required>
                    </label>

                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-numero"></i></span>
                        <span class="studentField__label">Número <b>*</b></span>
                        <input type="text" name="numero" placeholder="N." required>
                    </label>

                    <label class="studentField studentField--wide">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-bairro"></i></span>
                        <span class="studentField__label">Bairro <b>*</b></span>
                        <input type="text" name="bairro" placeholder="Nome do bairro" required>
                    </label>

                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-complemento"></i></span>
                        <span class="studentField__label">Complemento</span>
                        <input type="text" name="complemento" placeholder="Apto, casa, etc">
                    </label>

                    <label class="studentField studentField--wide">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-cidade"></i></span>
                        <span class="studentField__label">Cidade <b>*</b></span>
                        <input type="text" name="cidade" placeholder="Nome da cidade" required>
                    </label>

                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-estado"></i></span>
                        <span class="studentField__label">Estado <b>*</b></span>
                        <select name="estado" required>
                            <option value="">Selecione</option>
                            <option value="AC">AC</option>
                            <option value="AL">AL</option>
                            <option value="AP">AP</option>
                            <option value="AM">AM</option>
                            <option value="BA">BA</option>
                            <option value="CE">CE</option>
                            <option value="DF">DF</option>
                            <option value="ES">ES</option>
                            <option value="GO">GO</option>
                            <option value="MA">MA</option>
                            <option value="MT">MT</option>
                            <option value="MS">MS</option>
                            <option value="MG">MG</option>
                            <option value="PA">PA</option>
                            <option value="PB">PB</option>
                            <option value="PR">PR</option>
                            <option value="PE">PE</option>
                            <option value="PI">PI</option>
                            <option value="RJ">RJ</option>
                            <option value="RN">RN</option>
                            <option value="RS">RS</option>
                            <option value="RO">RO</option>
                            <option value="RR">RR</option>
                            <option value="SC">SC</option>
                            <option value="SP">SP</option>
                            <option value="SE">SE</option>
                            <option value="TO">TO</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="studentSignupBox">
                <h2><i class="icon-user" aria-hidden="true"></i> Sua conta</h2>

                <div class="studentSignupGrid studentSignupGrid--two">
                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-padlock"></i></span>
                        <span class="studentField__label">Senha <b>*</b></span>
                        <input type="password" name="senha" class="studentPassword" placeholder="Mínimo 8 caracteres" required>
                        <button class="studentField__toggle" type="button" aria-label="Mostrar senha"><i class="icon-ver" aria-hidden="true"></i></button>
                    </label>

                    <label class="studentField">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-padlock"></i></span>
                        <span class="studentField__label">Confirmar senha <b>*</b></span>
                        <input type="password" name="confirmar_senha" class="studentPassword" placeholder="Digite sua senha novamente" required>
                        <button class="studentField__toggle" type="button" aria-label="Mostrar senha"><i class="icon-ver" aria-hidden="true"></i></button>
                    </label>

                    <label class="studentField studentField--full">
                        <span class="studentField__icon" aria-hidden="true"><i class="icon-information"></i></span>
                        <span class="studentField__label">Como conheceu a MPG Academy? (opcional)</span>
                        <select name="origem">
                            <option value="">Selecione uma opção</option>
                            <option value="instagram">Instagram</option>
                            <option value="indicacao">Indicação</option>
                            <option value="google">Google</option>
                            <option value="evento">Evento</option>
                        </select>
                    </label>
                </div>
            </section>
        </div>

        <section class="studentSignupTerms">
            <label class="studentSignupTerms__check">
                <input type="checkbox" name="termos" required>
                <span></span>
            </label>

            <div>
                <p>Concordo com os <a href="#">Termos de Uso</a> e <a href="#">Política de Privacidade</a> da MPG Academy. <b>*</b></p>
                <small>Seus dados serão utilizados apenas para fins de comunicação e gestão da plataforma.</small>
            </div>

            <button class="studentSignupTerms__submit" type="submit">
                Criar minha conta
                <i class="icon-go" aria-hidden="true"></i>
            </button>
        </section>

        <p class="studentSignup__safe">
            <i class="icon-areadoaluno" aria-hidden="true"></i>
            Seus dados estão protegidos e seguros conosco.
        </p>
    </form>
</main>

<?php include ROOT . '/includes/scripts.php';?>
<?php
$version = time();
echo '<script src="' . BASE_URL . '/pages/cadastro/cadastro.js?' . $version . '"></script>';
?>

</body>
</html>

<?php
$isRegister = $modo === 'cadastro';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isRegister ? 'Criar conta' : 'Entrar' ?> | Meu Caderno</title>
<link rel="stylesheet" href="stylo.css">
</head>
<body class="auth-page">
<main class="auth-card">
  <a class="auth-logo serif" href="?acao=login">Meu Caderno</a>
  <p class="auth-intro">Suas receitas favoritas, guardadas em um só lugar.</p>
  <div class="auth-tabs" role="tablist" aria-label="Acesso à conta">
    <a class="<?= $isRegister ? '' : 'on' ?>" href="?acao=login" role="tab" aria-selected="<?= $isRegister ? 'false' : 'true' ?>">Entrar</a>
    <a class="<?= $isRegister ? 'on' : '' ?>" href="?acao=cadastro" role="tab" aria-selected="<?= $isRegister ? 'true' : 'false' ?>">Criar conta</a>
  </div>
  <h1 class="serif"><?= $isRegister ? 'Crie sua conta' : 'Bem-vinda de volta' ?></h1>
  <p class="auth-description"><?= $isRegister ? 'Cadastre-se para começar a salvar suas receitas.' : 'Entre para acessar o seu caderno de receitas.' ?></p>
  <?php if ($mensagemErro !== ''): ?>
    <p class="auth-error" role="alert"><?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <form class="auth-form" method="post" action="?acao=<?= $isRegister ? 'cadastro' : 'login' ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($isRegister): ?>
      <label for="nome">Nome</label>
      <input id="nome" name="nome" type="text" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars($nomeInformado ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= htmlspecialchars($emailInformado ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <label for="senha">Senha</label>
    <input id="senha" name="senha" type="password" minlength="8" maxlength="255" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" required>
    <?php if ($isRegister): ?>
      <label for="confirmar_senha">Confirme a senha</label>
      <input id="confirmar_senha" name="confirmar_senha" type="password" minlength="8" maxlength="255" autocomplete="new-password" required>
    <?php endif; ?>
    <button class="btn p auth-submit" type="submit"><?= $isRegister ? 'Criar conta' : 'Entrar' ?></button>
  </form>
</main>
</body>
</html>

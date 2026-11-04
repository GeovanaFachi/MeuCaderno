<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function responderJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function verificarCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function redirecionar(string $action): never
{
    header('Location: ?acao=' . rawurlencode($action));
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$acao = is_string($_GET['acao'] ?? null) ? $_GET['acao'] : 'home';
$isApi = in_array($acao, ['salvar', 'excluir', 'favoritar', 'ingrediente', 'compartilhar'], true);

try {
    $pdo = require __DIR__ . '/../config/conexao.php';
} catch (PDOException $error) {
    error_log('Meu Caderno: falha ao conectar ao banco: ' . $error->getMessage());
    if ($isApi) {
        responderJson(['error' => 'Banco de dados indisponível. Confira a configuração e tente novamente.'], 503);
    }
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Banco de dados não configurado | Meu Caderno</title>
      <link rel="stylesheet" href="stylo.css">
    </head>
    <body class="auth-page">
      <main class="auth-card">
        <h1 class="serif">Banco de dados não configurado</h1>
        <p>Execute <strong>database/Script.sql</strong> no DBeaver e confira as credenciais em <strong>config/conexao.php</strong>.</p>
      </main>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../app/Models/UsuarioModel.php';
require_once __DIR__ . '/../app/Models/ReceitaModel.php';
require_once __DIR__ . '/../app/Controllers/UsuarioController.php';
require_once __DIR__ . '/../app/Controllers/ReceitaController.php';

$usuarioModel = new UsuarioModel($pdo);
$receitaController = new ReceitaController(new ReceitaModel($pdo));

if ($isApi) {
    if (!isset($_SESSION['usuario']['id'])) {
        responderJson(['error' => 'Entre na sua conta para salvar e editar receitas.'], 401);
    }

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verificarCsrf($csrfHeader)) {
        responderJson(['error' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 419);
    }

    $entrada = json_decode(file_get_contents('php://input'), true);
    if (!is_array($entrada) || json_last_error() !== JSON_ERROR_NONE) {
        responderJson(['error' => 'Os dados enviados são inválidos.'], 400);
    }

    try {
        $usuarioId = (int) $_SESSION['usuario']['id'];
        switch ($acao) {
            case 'salvar':
                responderJson(['recipe' => $receitaController->salvar($usuarioId, $entrada)]);
            case 'excluir':
                $receitaController->excluir($usuarioId, $entrada);
                responderJson(['success' => true]);
            case 'favoritar':
                responderJson(['recipe' => $receitaController->alternarFavorita($usuarioId, $entrada)]);
            case 'ingrediente':
                $receitaController->marcarIngrediente($usuarioId, $entrada);
                responderJson(['success' => true]);
            case 'compartilhar':
                responderJson(['recipe' => $receitaController->compartilhar($usuarioId, $entrada)]);
        }
    } catch (InvalidArgumentException $error) {
        responderJson(['error' => $error->getMessage()], 422);
    } catch (OutOfBoundsException $error) {
        responderJson(['error' => $error->getMessage()], 404);
    } catch (PDOException $error) {
        error_log('Meu Caderno: falha ao salvar receita: ' . $error->getMessage());
        responderJson(['error' => 'Não foi possível salvar os dados. Tente novamente.'], 500);
    } catch (RuntimeException $error) {
        error_log('Meu Caderno: falha ao processar receita: ' . $error->getMessage());
        responderJson(['error' => 'Não foi possível concluir a operação. Tente novamente.'], 500);
    }
}

if ($acao === 'sair') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !verificarCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        echo 'Sua sessão expirou. Volte à página anterior e tente novamente.';
        exit;
    }

    $_SESSION = [];
    session_regenerate_id(true);
    redirecionar('login');
}

if ($acao === 'login' || $acao === 'cadastro') {
    $modo = $acao;
    $mensagemErro = '';
    $nomeInformado = '';
    $emailInformado = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verificarCsrf($_POST['csrf_token'] ?? null)) {
            $mensagemErro = 'Sua sessão expirou. Atualize a página e tente novamente.';
        } else {
            $emailInformado = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
            try {
                $authController = new UsuarioController($usuarioModel);
                if ($modo === 'cadastro') {
                    $nomeInformado = is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '';
                    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
                    $confirmacao = is_string($_POST['confirmar_senha'] ?? null) ? $_POST['confirmar_senha'] : '';
                    if ($senha !== $confirmacao) {
                        throw new InvalidArgumentException('As senhas informadas não coincidem.');
                    }
                    $authController->cadastrar($nomeInformado, $emailInformado, $senha);
                    redirecionar('home');
                }

                $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
                if ($authController->autenticar($emailInformado, $senha)) {
                    redirecionar('home');
                }
                $mensagemErro = 'E-mail ou senha incorretos.';
            } catch (InvalidArgumentException $error) {
                $mensagemErro = $error->getMessage();
            } catch (PDOException $error) {
                if ($modo === 'cadastro' && $error->getCode() === '23000') {
                    $mensagemErro = 'Já existe uma conta cadastrada com esse e-mail.';
                } else {
                    error_log('Meu Caderno: falha de autenticação: ' . $error->getMessage());
                    $mensagemErro = 'Não foi possível concluir o acesso. Tente novamente.';
                }
            }
        }
    }

    require __DIR__ . '/../views/Autenticacao.php';
    exit;
}

if (!isset($_SESSION['usuario']['id'])) {
    redirecionar('login');
}

if ($acao !== 'home') {
    http_response_code(404);
    echo 'Página não encontrada.';
    exit;
}

try {
    $usuario = $_SESSION['usuario'];
    $recipes = $receitaController->listar((int) $usuario['id']);
} catch (PDOException $error) {
    error_log('Meu Caderno: falha ao buscar receitas: ' . $error->getMessage());
    http_response_code(500);
    echo 'Não foi possível carregar suas receitas. Tente novamente.';
    exit;
}

require __DIR__ . '/../views/Home.php';

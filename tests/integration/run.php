<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Models/UsuarioModel.php';
require_once __DIR__ . '/../../app/Models/ReceitaModel.php';
require_once __DIR__ . '/../../app/Controllers/UsuarioController.php';
require_once __DIR__ . '/../../app/Controllers/ReceitaController.php';

function integrationAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': esperado ' . var_export($expected, true)
            . ', recebido ' . var_export($actual, true)
        );
    }
}

function integrationAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function integrationCreateSchema(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS receitas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            nome VARCHAR(60) NOT NULL,
            categoria VARCHAR(20) NOT NULL,
            tempo_minutos SMALLINT UNSIGNED NOT NULL,
            porcoes SMALLINT UNSIGNED NOT NULL,
            nota DECIMAL(2,1) NOT NULL DEFAULT 4.0,
            descricao VARCHAR(400) NOT NULL DEFAULT '',
            observacoes VARCHAR(400) NOT NULL DEFAULT '',
            favorita TINYINT(1) NOT NULL DEFAULT 0,
            compartilhada TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_receitas_usuario_criado (usuario_id, criado_em),
            CONSTRAINT fk_receitas_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS ingredientes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            receita_id INT UNSIGNED NOT NULL,
            nome VARCHAR(150) NOT NULL,
            quantidade VARCHAR(100) NOT NULL DEFAULT '',
            posicao SMALLINT UNSIGNED NOT NULL,
            separado TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_ingredientes_receita_posicao (receita_id, posicao),
            CONSTRAINT fk_ingredientes_receita
                FOREIGN KEY (receita_id) REFERENCES receitas(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS etapas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            receita_id INT UNSIGNED NOT NULL,
            descricao VARCHAR(2000) NOT NULL,
            posicao SMALLINT UNSIGNED NOT NULL,
            UNIQUE KEY uq_etapas_receita_posicao (receita_id, posicao),
            CONSTRAINT fk_etapas_receita
                FOREIGN KEY (receita_id) REFERENCES receitas(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $db->exec($statement);
    }
}

function integrationRecipeInput(array $overrides = []): array
{
    return array_replace([
        'n' => 'Receita de integração',
        'c' => 'Bolos',
        't' => 45,
        'p' => 6,
        'rating' => 4.5,
        'desc' => 'Descrição da receita de integração',
        'note' => 'Observação inicial',
        'ingredients' => [['Farinha', '2 xícaras'], ['Ovos', '3 unidades']],
        'steps' => ['Misture os ingredientes.', 'Asse até dourar.'],
    ], $overrides);
}

$requiredEnvironment = [
    'MEUCADERNO_TEST_DB_HOST',
    'MEUCADERNO_TEST_DB_NAME',
    'MEUCADERNO_TEST_DB_USER',
];
$configuration = [];

foreach ($requiredEnvironment as $variable) {
    $value = getenv($variable);
    if ($value === false || $value === '') {
        fwrite(STDERR, "Configuração obrigatória ausente: {$variable}.\n");
        exit(2);
    }
    $configuration[$variable] = $value;
}

$databaseName = $configuration['MEUCADERNO_TEST_DB_NAME'];
if (!preg_match('/^[A-Za-z0-9_]+_test$/i', $databaseName)) {
    fwrite(STDERR, "Execução cancelada: o nome do banco de teste deve terminar em _test.\n");
    exit(2);
}

$password = getenv('MEUCADERNO_TEST_DB_PASSWORD');
if ($password === false) {
    fwrite(STDERR, "Defina MEUCADERNO_TEST_DB_PASSWORD (use valor vazio se não houver senha).\n");
    exit(2);
}

if (!extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) {
    fwrite(STDERR, "As extensões PHP pdo_mysql e mbstring são necessárias.\n");
    exit(2);
}

$port = getenv('MEUCADERNO_TEST_DB_PORT');
$dsn = 'mysql:host=' . $configuration['MEUCADERNO_TEST_DB_HOST']
    . ($port !== false && $port !== '' ? ';port=' . $port : '')
    . ';dbname=' . $databaseName . ';charset=utf8mb4';

try {
    $db = new PDO($dsn, $configuration['MEUCADERNO_TEST_DB_USER'], $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $connectedDatabase = $db->query('SELECT DATABASE()')->fetchColumn();
    integrationAssertSame($databaseName, $connectedDatabase, 'Banco conectado diferente do banco de teste');
    integrationCreateSchema($db);
} catch (Throwable $error) {
    fwrite(STDERR, "Falha ao preparar o banco de integração: " . $error->getMessage() . "\n");
    exit(2);
}

if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
    fwrite(STDERR, "Não foi possível iniciar a sessão PHP para os testes.\n");
    exit(2);
}

$suffix = bin2hex(random_bytes(8));
$testEmail = 'integration-' . $suffix . '@example.test';
$otherEmail = 'integration-other-' . $suffix . '@example.test';
$createdUserIds = [];
$recipeId = null;
$results = [];
$passed = 0;
$failed = 0;
$test = static function (string $name, callable $operation) use (&$results, &$passed, &$failed): void {
    try {
        $operation();
        $passed++;
        $results[] = "PASS: {$name}";
    } catch (Throwable $error) {
        $failed++;
        $results[] = "FAIL: {$name}\n  " . $error->getMessage();
    }
};

$userModel = new UsuarioModel($db);
$recipeModel = new ReceitaModel($db);
$userController = new UsuarioController($userModel);
$recipeController = new ReceitaController($recipeModel);

try {
    ob_start();

    $test('cadastra usuário no banco com senha hash e sessão autenticada', static function () use (
        $userController,
        $userModel,
        $testEmail,
        &$createdUserIds
    ): void {
        $userController->cadastrar('Usuário de integração', $testEmail, 'senha-integracao');
        $createdUserIds[] = (int) $_SESSION['usuario']['id'];
        $persistedUser = $userModel->buscarPorEmail($testEmail);

        integrationAssertTrue($persistedUser !== null, 'Usuário cadastrado não foi encontrado no banco.');
        integrationAssertTrue(password_verify('senha-integracao', $persistedUser['senha']), 'Senha não foi armazenada como hash válido.');
        integrationAssertTrue(!isset($_SESSION['usuario']['senha']), 'Senha foi incluída na sessão.');
    });

    $test('persiste receita e reconstrói ingredientes e etapas ordenados', static function () use (
        $recipeController,
        $userController,
        $userModel,
        &$recipeId,
        &$createdUserIds,
        $otherEmail
    ): void {
        $userController->cadastrar('Outro usuário', $otherEmail, 'outra-senha-segura');
        $createdUserIds[] = (int) $_SESSION['usuario']['id'];
        $otherUserId = (int) $_SESSION['usuario']['id'];

        $owner = $userModel->buscarPorEmail($otherEmail);
        integrationAssertTrue($owner !== null, 'Segundo usuário não foi persistido.');

        $recipe = $recipeController->salvar($otherUserId, integrationRecipeInput());
        $recipeId = $recipe['id'];
        integrationAssertSameValue('Receita de integração', $recipe['n'], 'Nome da receita');
        integrationAssertSameValue([['Farinha', '2 xícaras'], ['Ovos', '3 unidades']], $recipe['ingredients'], 'Ingredientes persistidos');
        integrationAssertSameValue(['Misture os ingredientes.', 'Asse até dourar.'], $recipe['steps'], 'Etapas persistidas');
        integrationAssertSameValue([], $recipe['checkedIngredients'], 'Ingredientes inicialmente não marcados');
    });

    $test('atualiza receita e substitui ingredientes e etapas transacionalmente', static function () use (
        $recipeController,
        $recipeModel,
        $userModel,
        $otherEmail,
        &$recipeId
    ): void {
        $owner = $userModel->buscarPorEmail($otherEmail);
        integrationAssertTrue($owner !== null && $recipeId !== null, 'Dados de preparação ausentes.');
        $userId = (int) $owner['id'];
        $updated = $recipeController->salvar($userId, integrationRecipeInput([
            'id' => $recipeId,
            'n' => 'Receita atualizada',
            'ingredients' => [['Açúcar', '1 xícara']],
            'steps' => ['Leve ao forno.'],
        ]));

        integrationAssertSameValue($recipeId, $updated['id'], 'Identificador preservado na edição');
        integrationAssertSameValue('Receita atualizada', $updated['n'], 'Nome atualizado');
        integrationAssertSameValue([['Açúcar', '1 xícara']], $updated['ingredients'], 'Ingredientes substituídos');
        integrationAssertSameValue(['Leve ao forno.'], $updated['steps'], 'Etapas substituídas');
        integrationAssertSameValue($updated, $recipeModel->buscarPorId($userId, $recipeId), 'Receita persistida após atualização');
    });

    $test('persiste favorito, compartilhamento e estado de ingrediente', static function () use (
        $recipeController,
        $userModel,
        $otherEmail,
        &$recipeId
    ): void {
        $owner = $userModel->buscarPorEmail($otherEmail);
        integrationAssertTrue($owner !== null && $recipeId !== null, 'Dados de preparação ausentes.');
        $userId = (int) $owner['id'];

        integrationAssertSameValue(true, $recipeController->alternarFavorita($userId, ['id' => $recipeId])['f'], 'Favorita ativada');
        integrationAssertSameValue(true, $recipeController->compartilhar($userId, ['id' => $recipeId])['shared'], 'Compartilhamento persistido');
        $recipeController->marcarIngrediente($userId, ['id' => $recipeId, 'index' => 0, 'checked' => true]);
        integrationAssertSameValue([0], $recipeController->listar($userId)[0]['checkedIngredients'], 'Ingrediente marcado');
        $recipeController->marcarIngrediente($userId, ['id' => $recipeId, 'index' => 0, 'checked' => false]);
        integrationAssertSameValue([], $recipeController->listar($userId)[0]['checkedIngredients'], 'Ingrediente desmarcado');
    });

    $test('impede leitura e alterações por usuário diferente do proprietário', static function () use (
        $recipeController,
        $recipeModel,
        $userModel,
        $testEmail,
        &$recipeId
    ): void {
        $otherUser = $userModel->buscarPorEmail($testEmail);
        integrationAssertTrue($otherUser !== null && $recipeId !== null, 'Dados de preparação ausentes.');
        $otherUserId = (int) $otherUser['id'];

        integrationAssertSameValue(null, $recipeModel->buscarPorId($otherUserId, $recipeId), 'Leitura de receita alheia');
        integrationAssertSameValue(false, $recipeModel->atualizarIngrediente($otherUserId, $recipeId, 0, true), 'Alteração de ingrediente alheio');
        integrationAssertSameValue(false, $recipeModel->excluir($otherUserId, $recipeId), 'Exclusão de receita alheia');
        integrationAssertSameValue(null, $recipeModel->buscarPorId($otherUserId, $recipeId), 'Receita continua invisível ao outro usuário');
    });

    $test('exclui receita do proprietário e remove os registros dependentes', static function () use (
        $recipeController,
        $recipeModel,
        $userModel,
        $otherEmail,
        $db,
        &$recipeId
    ): void {
        $owner = $userModel->buscarPorEmail($otherEmail);
        integrationAssertTrue($owner !== null && $recipeId !== null, 'Dados de preparação ausentes.');
        $userId = (int) $owner['id'];

        $recipeController->excluir($userId, ['id' => $recipeId]);
        integrationAssertSameValue(null, $recipeModel->buscarPorId($userId, $recipeId), 'Receita removida');

        foreach (['ingredientes', 'etapas'] as $table) {
            $statement = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE receita_id = :recipe_id");
            $statement->execute(['recipe_id' => $recipeId]);
            integrationAssertSameValue(0, (int) $statement->fetchColumn(), "Registros dependentes removidos de {$table}");
        }
    });
} finally {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    foreach (array_unique($createdUserIds) as $userId) {
        $statement = $db->prepare('DELETE FROM usuarios WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }
}

echo implode("\n", $results) . "\n";
printf("\nResultado: %d aprovados; %d falharam; %d no total.\n", $passed, $failed, count($results));
exit($failed === 0 && count($results) === 6 ? 0 : 1);

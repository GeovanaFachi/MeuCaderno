<?php

declare(strict_types=1);

function e2eFail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(2);
}

function e2eAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': esperado ' . var_export($expected, true)
            . ', recebido ' . var_export($actual, true)
        );
    }
}

function e2eAssertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function e2eRequest(string $url, string $cookieFile, string $method = 'GET', array $options = []): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Não foi possível inicializar cURL.');
    }

    $headers = $options['headers'] ?? [];
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if (isset($options['body'])) {
        $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
    }

    curl_setopt_array($curl, $curlOptions);
    $rawResponse = curl_exec($curl);
    if ($rawResponse === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Falha na requisição HTTP: ' . $message);
    }

    $headerEnd = strrpos($rawResponse, "\r\n\r\n");
    if ($headerEnd === false) {
        curl_close($curl);
        throw new RuntimeException('A resposta HTTP não contém cabeçalhos válidos.');
    }
    $responseHeaders = substr($rawResponse, 0, $headerEnd);
    $body = substr($rawResponse, $headerEnd + 4);
    preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $locationMatch);
    $response = [
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'location' => trim($locationMatch[1] ?? ''),
        'body' => $body,
    ];
    curl_close($curl);

    return $response;
}

function e2eCsrfToken(string $html): string
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new RuntimeException('Não foi possível analisar o HTML para localizar o token CSRF.');
    }

    $xpath = new DOMXPath($document);
    $token = $xpath->query('//input[@name="csrf_token"]')->item(0)?->getAttribute('value');
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Token CSRF não encontrado na página.');
    }

    return $token;
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
        e2eFail("Configuração obrigatória ausente: {$variable}.");
    }
    $configuration[$variable] = $value;
}

$databaseName = $configuration['MEUCADERNO_TEST_DB_NAME'];
if (!preg_match('/^[A-Za-z0-9_]+_test$/i', $databaseName)) {
    e2eFail('Execução cancelada: o nome do banco de teste deve terminar em _test.');
}

$password = getenv('MEUCADERNO_TEST_DB_PASSWORD');
if ($password === false) {
    e2eFail('Defina MEUCADERNO_TEST_DB_PASSWORD (use valor vazio se não houver senha).');
}

if (!extension_loaded('pdo_mysql') || !extension_loaded('curl')
    || !extension_loaded('dom') || !extension_loaded('mbstring')) {
    e2eFail('As extensões PHP pdo_mysql, curl, dom e mbstring são necessárias.');
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
    e2eAssertSame($databaseName, $connectedDatabase, 'Banco conectado diferente do banco de teste');
    $db->query('SELECT id FROM usuarios LIMIT 0');
} catch (Throwable $error) {
    e2eFail('Não foi possível conectar ao banco/schema de teste: ' . $error->getMessage());
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if ($socket === false) {
    e2eFail("Não foi possível reservar uma porta local: {$socketMessage} ({$socketError}).");
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
if (!is_string($address) || !preg_match('/:(\d+)$/', $address, $portMatch)) {
    e2eFail('Não foi possível determinar a porta local para o servidor de teste.');
}
$serverPort = (int) $portMatch[1];

$temporaryFiles = [];
$cookieFile = tempnam(sys_get_temp_dir(), 'meucaderno-e2e-cookie-');
$serverLog = tempnam(sys_get_temp_dir(), 'meucaderno-e2e-server-');
if ($cookieFile === false || $serverLog === false) {
    e2eFail('Não foi possível criar arquivos temporários para os testes.');
}
$temporaryFiles[] = $cookieFile;
$temporaryFiles[] = $serverLog;

$logHandle = fopen($serverLog, 'ab');
if ($logHandle === false) {
    e2eFail('Não foi possível abrir o log temporário do servidor.');
}

$publicDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
$command = '"' . PHP_BINARY . '" -S 127.0.0.1:' . $serverPort
    . ' -t "' . $publicDirectory . '"';
$serverEnvironment = getenv();
if (!is_array($serverEnvironment)) {
    $serverEnvironment = [];
}
$serverEnvironment['MEUCADERNO_DB_HOST'] = $configuration['MEUCADERNO_TEST_DB_HOST'];
$serverEnvironment['MEUCADERNO_DB_NAME'] = $databaseName;
$serverEnvironment['MEUCADERNO_DB_USER'] = $configuration['MEUCADERNO_TEST_DB_USER'];
$serverEnvironment['MEUCADERNO_DB_PASSWORD'] = $password;
$serverEnvironment['MEUCADERNO_DB_PORT'] = $port !== false ? $port : '';

$server = proc_open($command, [
    0 => ['pipe', 'r'],
    1 => $logHandle,
    2 => $logHandle,
], $serverPipes, dirname(__DIR__, 2), $serverEnvironment);

if (!is_resource($server)) {
    fclose($logHandle);
    e2eFail('Não foi possível iniciar o servidor PHP de teste.');
}
fclose($serverPipes[0]);

$email = 'e2e-' . bin2hex(random_bytes(8)) . '@example.test';
$results = [];
$passed = 0;
$failed = 0;
$recipeId = null;
$csrfToken = null;
$baseUrl = 'http://127.0.0.1:' . $serverPort . '/';
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

try {
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $serverStatus = proc_get_status($server);
        if (!$serverStatus['running']) {
            break;
        }

        $probe = curl_init($baseUrl . '?acao=login');
        curl_setopt_array($probe, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
        ]);
        curl_exec($probe);
        $probeError = curl_errno($probe);
        curl_close($probe);
        if ($probeError === 0) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        $serverStatus = proc_get_status($server);
        throw new RuntimeException(
            'Servidor PHP não ficou disponível. Estado do processo: '
            . ($serverStatus['running'] ? 'em execução' : 'encerrado')
            . '. Consulte o log temporário do servidor.'
        );
    }

    $test('redireciona visitante sem sessão para login', static function () use ($baseUrl, $cookieFile): void {
        $response = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(302, $response['status'], 'Status de visitante');
        e2eAssertTrue(str_ends_with($response['location'], '?acao=login'), 'Destino do redirecionamento para login incorreto.');
    });

    $test('renderiza formulário de cadastro com token CSRF', static function () use (
        $baseUrl,
        $cookieFile,
        &$csrfToken
    ): void {
        $response = e2eRequest($baseUrl . '?acao=cadastro', $cookieFile);
        e2eAssertSame(200, $response['status'], 'Status da página de cadastro');
        e2eAssertTrue(str_contains($response['body'], 'Crie sua conta'), 'Título de cadastro não encontrado.');
        $csrfToken = e2eCsrfToken($response['body']);
        e2eAssertTrue(strlen($csrfToken) === 64, 'Token CSRF não tem o formato esperado.');
    });

    $test('rejeita confirmação de senha divergente sem criar sessão de usuário', static function () use (
        $baseUrl,
        $cookieFile,
        &$csrfToken,
        $email
    ): void {
        e2eAssertTrue(is_string($csrfToken), 'Token CSRF indisponível.');
        $response = e2eRequest($baseUrl . '?acao=cadastro', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'csrf_token' => $csrfToken,
                'nome' => 'Usuário E2E',
                'email' => $email,
                'senha' => 'senha-e2e-segura',
                'confirmar_senha' => 'senha-diferente',
            ]),
        ]);
        e2eAssertSame(200, $response['status'], 'Status ao validar senhas diferentes');
        e2eAssertTrue(str_contains($response['body'], 'As senhas informadas não coincidem.'), 'Mensagem de divergência não exibida.');
    });

    $test('cadastra usuário e abre a página inicial autenticada', static function () use (
        $baseUrl,
        $cookieFile,
        &$csrfToken,
        $email
    ): void {
        e2eAssertTrue(is_string($csrfToken), 'Token CSRF indisponível.');
        $response = e2eRequest($baseUrl . '?acao=cadastro', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'csrf_token' => $csrfToken,
                'nome' => 'Usuário E2E',
                'email' => $email,
                'senha' => 'senha-e2e-segura',
                'confirmar_senha' => 'senha-e2e-segura',
            ]),
        ]);
        e2eAssertSame(302, $response['status'], 'Status após cadastro');
        e2eAssertTrue(str_ends_with($response['location'], '?acao=home'), 'Cadastro não redirecionou para a página inicial.');
        $home = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(200, $home['status'], 'Status da página inicial autenticada');
        e2eAssertTrue(str_contains($home['body'], 'Usuário E2E'), 'Nome do usuário não aparece na página inicial.');
        e2eAssertTrue(str_contains($home['body'], 'Nova receita'), 'Ação para criar receita não aparece.');
        $csrfToken = e2eCsrfToken($home['body']);
    });

    $test('rejeita requisição de API com token CSRF inválido', static function () use ($baseUrl, $cookieFile): void {
        $response = e2eRequest($baseUrl . '?acao=salvar', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: inválido'],
            'body' => '{}',
        ]);
        e2eAssertSame(419, $response['status'], 'Status da requisição com CSRF inválido');
    });

    $test('cria receita pela API e renderiza a receita persistida', static function () use (
        $baseUrl,
        $cookieFile,
        &$recipeId,
        &$csrfToken
    ): void {
        $token = $csrfToken;
        e2eAssertTrue(is_string($token), 'Token CSRF da página inicial indisponível.');
        $response = e2eRequest($baseUrl . '?acao=salvar', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
            'body' => json_encode([
                'n' => 'Bolo E2E',
                'c' => 'Bolos',
                't' => 35,
                'p' => 8,
                'rating' => 4.5,
                'desc' => 'Receita criada pelo fluxo HTTP.',
                'note' => 'Observação de teste.',
                'ingredients' => [['Farinha', '2 xícaras'], ['Ovos', '3 unidades']],
                'steps' => ['Misture.', 'Asse.'],
            ], JSON_THROW_ON_ERROR),
        ]);
        e2eAssertSame(200, $response['status'], 'Status ao criar receita pela API');
        $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        e2eAssertTrue(isset($payload['recipe']['id']), 'Resposta não contém receita criada.');
        $recipeId = (int) $payload['recipe']['id'];
        e2eAssertSame('Bolo E2E', $payload['recipe']['n'], 'Nome da receita na resposta');
        e2eAssertSame([['Farinha', '2 xícaras'], ['Ovos', '3 unidades']], $payload['recipe']['ingredients'], 'Ingredientes na resposta');
        e2eAssertSame(['Misture.', 'Asse.'], $payload['recipe']['steps'], 'Etapas na resposta');

        $home = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(200, $home['status'], 'Status da página inicial após salvar');
        e2eAssertTrue(str_contains($home['body'], 'Bolo E2E'), 'Receita salva não foi incluída na página inicial.');
    });

    $test('persiste favorito, compartilhamento e ingrediente marcado via API', static function () use (
        $baseUrl,
        $cookieFile,
        &$recipeId,
        &$csrfToken
    ): void {
        $token = $csrfToken;
        e2eAssertTrue(is_string($token) && $recipeId !== null, 'Sessão ou receita de teste indisponível.');

        foreach ([
            ['favoritar', ['id' => $recipeId], static function (array $recipe): void {
                e2eAssertSame(true, $recipe['f'], 'Receita marcada como favorita');
            }],
            ['compartilhar', ['id' => $recipeId], static function (array $recipe): void {
                e2eAssertSame(true, $recipe['shared'], 'Receita marcada como compartilhada');
            }],
        ] as [$action, $input, $assertion]) {
            $response = e2eRequest($baseUrl . '?acao=' . $action, $cookieFile, 'POST', [
                'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
                'body' => json_encode($input, JSON_THROW_ON_ERROR),
            ]);
            e2eAssertSame(200, $response['status'], 'Status da ação ' . $action);
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            $assertion($payload['recipe']);
        }

        $response = e2eRequest($baseUrl . '?acao=ingrediente', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
            'body' => json_encode(['id' => $recipeId, 'index' => 0, 'checked' => true], JSON_THROW_ON_ERROR),
        ]);
        e2eAssertSame(200, $response['status'], 'Status ao marcar ingrediente');
        e2eAssertSame(['success' => true], json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR), 'Resposta ao marcar ingrediente');

        $home = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        preg_match('/const CSRF_TOKEN = "([^"]+)";/', $home['body'], $tokenMatch);
        e2eAssertTrue(isset($tokenMatch[1]), 'Token da página inicial não encontrado após atualização.');
        $recipeData = $home['body'];
        e2eAssertTrue(str_contains($recipeData, '"checkedIngredients":[0]'), 'Estado do ingrediente marcado não foi renderizado.');
        $csrfToken = $tokenMatch[1];
    });

    $test('rejeita dados inválidos e operação sobre receita inexistente', static function () use (
        $baseUrl,
        $cookieFile,
        &$recipeId,
        &$csrfToken
    ): void {
        $token = $csrfToken;
        e2eAssertTrue(is_string($token) && $recipeId !== null, 'Sessão ou receita de teste indisponível.');

        $invalid = e2eRequest($baseUrl . '?acao=salvar', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
            'body' => json_encode(['n' => '', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0], JSON_THROW_ON_ERROR),
        ]);
        e2eAssertSame(422, $invalid['status'], 'Status para entrada inválida');

        $missing = e2eRequest($baseUrl . '?acao=excluir', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
            'body' => json_encode(['id' => 2147483647], JSON_THROW_ON_ERROR),
        ]);
        e2eAssertSame(404, $missing['status'], 'Status para receita inexistente');
    });

    $test('exclui receita, encerra sessão e autentica novamente', static function () use (
        $baseUrl,
        $cookieFile,
        &$recipeId,
        &$csrfToken,
        $email
    ): void {
        $token = $csrfToken;
        e2eAssertTrue(is_string($token) && $recipeId !== null, 'Sessão ou receita de teste indisponível.');

        $deleted = e2eRequest($baseUrl . '?acao=excluir', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
            'body' => json_encode(['id' => $recipeId], JSON_THROW_ON_ERROR),
        ]);
        e2eAssertSame(200, $deleted['status'], 'Status ao excluir receita');
        e2eAssertSame(['success' => true], json_decode($deleted['body'], true, 512, JSON_THROW_ON_ERROR), 'Resposta ao excluir receita');

        $home = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(200, $home['status'], 'Status da página inicial após exclusão');
        e2eAssertTrue(!str_contains($home['body'], 'Bolo E2E'), 'Receita excluída ainda aparece na página.');
        $logoutToken = e2eCsrfToken($home['body']);

        $logout = e2eRequest($baseUrl . '?acao=sair', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query(['csrf_token' => $logoutToken]),
        ]);
        e2eAssertSame(302, $logout['status'], 'Status ao sair');
        e2eAssertTrue(str_ends_with($logout['location'], '?acao=login'), 'Logout não redirecionou para login.');

        $guest = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(302, $guest['status'], 'Status após sessão encerrada');

        $loginPage = e2eRequest($baseUrl . '?acao=login', $cookieFile);
        e2eAssertSame(200, $loginPage['status'], 'Status da página de login');
        e2eAssertTrue(str_contains($loginPage['body'], 'Bem-vinda de volta'), 'Formulário de login não foi renderizado.');
        $loginToken = e2eCsrfToken($loginPage['body']);
        $login = e2eRequest($baseUrl . '?acao=login', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'csrf_token' => $loginToken,
                'email' => $email,
                'senha' => 'senha-e2e-segura',
            ]),
        ]);
        e2eAssertSame(302, $login['status'], 'Status após login');
        e2eAssertTrue(str_ends_with($login['location'], '?acao=home'), 'Login não redirecionou para a página inicial.');

        $authenticatedHome = e2eRequest($baseUrl . '?acao=home', $cookieFile);
        e2eAssertSame(200, $authenticatedHome['status'], 'Status da página inicial após novo login');
        e2eAssertTrue(str_contains($authenticatedHome['body'], 'Usuário E2E'), 'Usuário não autenticado na página inicial após login.');
        $finalLogout = e2eRequest($baseUrl . '?acao=sair', $cookieFile, 'POST', [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => http_build_query(['csrf_token' => e2eCsrfToken($authenticatedHome['body'])]),
        ]);
        e2eAssertSame(302, $finalLogout['status'], 'Status ao encerrar a sessão após login');
    });
} finally {
    try {
        $statement = $db->prepare('DELETE FROM usuarios WHERE email = :email');
        $statement->execute(['email' => $email]);
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }

        fclose($logHandle);
        foreach ($temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}

echo implode("\n", $results) . "\n";
printf("\nResultado: %d aprovados; %d falharam; %d no total.\n", $passed, $failed, count($results));
exit($failed === 0 && count($results) === 9 ? 0 : 1);

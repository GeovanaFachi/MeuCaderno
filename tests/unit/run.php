<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Models/ReceitaModel.php';
require_once __DIR__ . '/../../app/Models/UsuarioModel.php';
require_once __DIR__ . '/../../app/Controllers/ReceitaController.php';
require_once __DIR__ . '/../../app/Controllers/UsuarioController.php';

final class FakeReceitaModel extends ReceitaModel
{
    public array $listedRecipes = [];
    public array $savedData = [];
    public int $savedUserId = 0;
    public int $savedId = 42;
    public ?array $recipe = ['id' => 42, 'n' => 'Bolo'];
    public ?array $actionRecipe = ['id' => 42, 'f' => true, 'shared' => true];
    public bool $ingredientUpdated = true;
    public bool $deleted = true;

    public function __construct()
    {
    }

    public function buscarTodasPorUsuario(int $usuarioId): array
    {
        return $this->listedRecipes;
    }

    public function salvar(int $usuarioId, array $dados): int
    {
        $this->savedUserId = $usuarioId;
        $this->savedData = $dados;

        return $this->savedId;
    }

    public function buscarPorId(int $usuarioId, int $receitaId): ?array
    {
        return $this->recipe;
    }

    public function alternarFavorita(int $usuarioId, int $receitaId): ?array
    {
        return $this->actionRecipe;
    }

    public function marcarCompartilhada(int $usuarioId, int $receitaId): ?array
    {
        return $this->actionRecipe;
    }

    public function atualizarIngrediente(int $usuarioId, int $receitaId, int $posicao, bool $separado): bool
    {
        return $this->ingredientUpdated;
    }

    public function excluir(int $usuarioId, int $receitaId): bool
    {
        return $this->deleted;
    }
}

final class FakeUsuarioModel extends UsuarioModel
{
    public ?array $user = null;
    public array $createdUser = [];
    public int $createdId = 7;

    public function __construct()
    {
    }

    public function buscarPorEmail(string $email): ?array
    {
        $this->createdUser['searchedEmail'] = $email;

        return $this->user;
    }

    public function criar(string $nome, string $email, string $senha): int
    {
        $this->createdUser = compact('nome', 'email', 'senha');

        return $this->createdId;
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '')
            . 'esperado ' . var_export($expected, true)
            . ', recebido ' . var_export($actual, true)
        );
    }
}

function assertThrows(string $exceptionClass, callable $operation): Throwable
{
    try {
        $operation();
    } catch (Throwable $error) {
        if (!$error instanceof $exceptionClass) {
            throw new RuntimeException(
                'Esperada exceção ' . $exceptionClass . ', recebida ' . get_class($error),
                0,
                $error
            );
        }

        return $error;
    }

    throw new RuntimeException('Esperada exceção ' . $exceptionClass . ', mas nenhuma foi lançada.');
}

$tests = [];
$test = static function (string $name, callable $operation) use (&$tests): void {
    $tests[$name] = $operation;
};

$test('lista receitas encaminhando o usuário ao modelo', static function (): void {
    $model = new FakeReceitaModel();
    $model->listedRecipes = [['id' => 1]];

    assertSameValue($model->listedRecipes, (new ReceitaController($model))->listar(12));
});

$test('salva receita validada e normaliza textos, ingredientes e etapas', static function (): void {
    $model = new FakeReceitaModel();
    $controller = new ReceitaController($model);
    $result = $controller->salvar(12, [
        'n' => '  Bolo  ',
        'c' => 'Bolos',
        't' => '30',
        'p' => '8',
        'rating' => '4.5',
        'desc' => '  Descrição  ',
        'note' => '  Observação  ',
        'ingredients' => [[' Farinha ', ' 2 xícaras '], [' ', 'irrelevante']],
        'steps' => [' Misture ', '  '],
    ]);

    assertSameValue(12, $model->savedUserId);
    assertSameValue([
        'id' => null,
        'n' => 'Bolo',
        'c' => 'Bolos',
        't' => 30,
        'p' => 8,
        'rating' => 4.5,
        'desc' => 'Descrição',
        'note' => 'Observação',
        'ingredients' => [['Farinha', '2 xícaras']],
        'steps' => ['Misture'],
    ], $model->savedData);
    assertSameValue($model->recipe, $result);
});

$test('aceita edição com identificador positivo', static function (): void {
    $model = new FakeReceitaModel();
    $model->recipe = ['id' => 8];
    (new ReceitaController($model))->salvar(3, [
        'id' => '8', 'n' => 'Sopa', 'c' => 'Saladas', 't' => 1, 'p' => 1,
        'rating' => 0, 'desc' => '', 'note' => '', 'ingredients' => [], 'steps' => [],
    ]);

    assertSameValue(8, $model->savedData['id']);
});

$test('rejeita categoria desconhecida', static function (): void {
    assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
        'n' => 'Bolo', 'c' => 'Outra', 't' => 1, 'p' => 1, 'rating' => 0,
    ]));
});

$test('rejeita tempo fora do intervalo permitido', static function (): void {
    foreach ([0, 10001, 'inválido'] as $time) {
        assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => $time, 'p' => 1, 'rating' => 0,
        ]));
    }
});

$test('rejeita rendimento fora do intervalo permitido', static function (): void {
    foreach ([0, 10001, 'inválido'] as $servings) {
        assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => $servings, 'rating' => 0,
        ]));
    }
});

$test('aceita nota nos extremos e em meio ponto', static function (): void {
    foreach ([0, 0.5, 5] as $rating) {
        $model = new FakeReceitaModel();
        (new ReceitaController($model))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => $rating,
        ]);
        assertSameValue((float) $rating, $model->savedData['rating']);
    }
});

$test('rejeita nota fora do intervalo ou diferente de meio ponto', static function (): void {
    foreach ([-0.5, 5.5, 2.25, 'infinito'] as $rating) {
        assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => $rating,
        ]));
    }
});

$test('rejeita identificador de receita inválido', static function (): void {
    assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
        'id' => '0', 'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
    ]));
});

$test('rejeita nome obrigatório vazio', static function (): void {
    assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
        'n' => '  ', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
    ]));
});

$test('rejeita coleção de ingredientes inválida', static function (): void {
    foreach (['não é lista', [['ingrediente sem quantidade']]] as $ingredients) {
        assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
            'ingredients' => $ingredients,
        ]));
    }
});

$test('rejeita lista com mais de cem ingredientes', static function (): void {
    assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
        'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
        'ingredients' => array_fill(0, 101, ['Farinha', '1']),
    ]));
});

$test('rejeita etapas que não são texto ou excedem o limite', static function (): void {
    foreach ([[123], [str_repeat('a', 2001)]] as $steps) {
        assertThrows(InvalidArgumentException::class, static fn() => (new ReceitaController(new FakeReceitaModel()))->salvar(1, [
            'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
            'steps' => $steps,
        ]));
    }
});

$test('lança erro se receita salva não puder ser recarregada', static function (): void {
    $model = new FakeReceitaModel();
    $model->recipe = null;
    assertThrows(RuntimeException::class, static fn() => (new ReceitaController($model))->salvar(1, [
        'n' => 'Bolo', 'c' => 'Bolos', 't' => 1, 'p' => 1, 'rating' => 0,
    ]));
});

$test('retorna receita ao alternar favorita e compartilhar', static function (): void {
    $model = new FakeReceitaModel();
    $controller = new ReceitaController($model);

    assertSameValue($model->actionRecipe, $controller->alternarFavorita(1, ['id' => '42']));
    assertSameValue($model->actionRecipe, $controller->compartilhar(1, ['id' => 42]));
});

$test('retorna erro se receita não existir ao alternar ou compartilhar', static function (): void {
    $model = new FakeReceitaModel();
    $model->actionRecipe = null;
    $controller = new ReceitaController($model);

    assertThrows(OutOfBoundsException::class, static fn() => $controller->alternarFavorita(1, ['id' => 42]));
    assertThrows(OutOfBoundsException::class, static fn() => $controller->compartilhar(1, ['id' => 42]));
});

$test('valida e marca ingrediente', static function (): void {
    (new ReceitaController(new FakeReceitaModel()))->marcarIngrediente(1, [
        'id' => 42, 'index' => '0', 'checked' => false,
    ]);
});

$test('rejeita posição e estado inválidos de ingrediente', static function (): void {
    $controller = new ReceitaController(new FakeReceitaModel());
    foreach ([
        ['id' => 42, 'index' => -1, 'checked' => true],
        ['id' => 42, 'index' => 0, 'checked' => 'true'],
    ] as $input) {
        assertThrows(InvalidArgumentException::class, static fn() => $controller->marcarIngrediente(1, $input));
    }
});

$test('lança erro quando ingrediente não existe', static function (): void {
    $model = new FakeReceitaModel();
    $model->ingredientUpdated = false;
    assertThrows(OutOfBoundsException::class, static fn() => (new ReceitaController($model))->marcarIngrediente(1, [
        'id' => 42, 'index' => 0, 'checked' => true,
    ]));
});

$test('exclui receita existente e sinaliza receita ausente', static function (): void {
    $controller = new ReceitaController(new FakeReceitaModel());
    $controller->excluir(1, ['id' => 42]);

    $model = new FakeReceitaModel();
    $model->deleted = false;
    assertThrows(OutOfBoundsException::class, static fn() => (new ReceitaController($model))->excluir(1, ['id' => 42]));
});

$test('autentica com e-mail normalizado e não expõe senha na sessão', static function (): void {
    $_SESSION = [];
    $model = new FakeUsuarioModel();
    $model->user = [
        'id' => 7,
        'nome' => 'Ana',
        'email' => 'ana@example.com',
        'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
    ];

    assertSameValue(true, (new UsuarioController($model))->autenticar(' ANA@EXAMPLE.COM ', 'senha-segura'));
    assertSameValue('ana@example.com', $model->createdUser['searchedEmail']);
    assertSameValue(['id' => 7, 'nome' => 'Ana', 'email' => 'ana@example.com'], $_SESSION['usuario']);
});

$test('retorna falso para usuário ausente ou senha incorreta', static function (): void {
    $model = new FakeUsuarioModel();
    $controller = new UsuarioController($model);
    assertSameValue(false, $controller->autenticar('naoexiste@example.com', 'senha'));

    $model->user = ['id' => 7, 'senha' => password_hash('correta', PASSWORD_DEFAULT)];
    assertSameValue(false, $controller->autenticar('ana@example.com', 'incorreta'));
});

$test('cadastra usuário com dados normalizados na sessão', static function (): void {
    $_SESSION = [];
    $model = new FakeUsuarioModel();
    (new UsuarioController($model))->cadastrar(' Ana ', ' ANA@EXAMPLE.COM ', 'senha-segura');

    assertSameValue('Ana', $model->createdUser['nome']);
    assertSameValue('ana@example.com', $model->createdUser['email']);
    assertSameValue('senha-segura', $model->createdUser['senha']);
    assertSameValue(['id' => 7, 'nome' => 'Ana', 'email' => 'ana@example.com'], $_SESSION['usuario']);
});

$test('rejeita nome, e-mail ou senha inválidos no cadastro', static function (): void {
    $controller = new UsuarioController(new FakeUsuarioModel());
    foreach ([
        ['', 'ana@example.com', 'senha-segura'],
        [str_repeat('a', 101), 'ana@example.com', 'senha-segura'],
        ['Ana', 'email-invalido', 'senha-segura'],
        ['Ana', 'ana@example.com', 'curta'],
    ] as [$name, $email, $password]) {
        assertThrows(InvalidArgumentException::class, static fn() => $controller->cadastrar($name, $email, $password));
    }
});

if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão para os testes de autenticação.');
}

$passed = 0;
$failed = 0;
$results = [];

foreach ($tests as $name => $operation) {
    try {
        $operation();
        $passed++;
        $results[] = "PASS: {$name}";
    } catch (Throwable $error) {
        $failed++;
        $results[] = "FAIL: {$name}\n  " . $error->getMessage();
    }
}

echo implode("\n", $results) . "\n";
printf("\nResultado: %d aprovados; %d falharam; %d no total.\n", $passed, $failed, count($tests));
exit($failed === 0 ? 0 : 1);

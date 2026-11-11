<?php

require_once __DIR__ . '/../Models/ReceitaModel.php';

class ReceitaController
{
    private const CATEGORIAS = ['Bolos', 'Doces', 'Salgados', 'Massas', 'Saladas', 'Bebidas'];

    public function __construct(private ReceitaModel $model)
    {
    }

    public function listar(int $usuarioId): array
    {
        return $this->model->buscarTodasPorUsuario($usuarioId);
    }

    public function salvar(int $usuarioId, array $entrada): array
    {
        $nome = $this->texto($entrada['n'] ?? null, 'nome da receita', 60, true);
        $categoria = $this->texto($entrada['c'] ?? null, 'categoria', 20, true);
        if (!in_array($categoria, self::CATEGORIAS, true)) {
            throw new InvalidArgumentException('Selecione uma categoria válida.');
        }

        $tempo = filter_var($entrada['t'] ?? null, FILTER_VALIDATE_INT);
        $porcoes = filter_var($entrada['p'] ?? null, FILTER_VALIDATE_INT);
        if ($tempo === false || $tempo < 1 || $tempo > 10000) {
            throw new InvalidArgumentException('O tempo deve ser um número entre 1 e 10000 minutos.');
        }
        if ($porcoes === false || $porcoes < 1 || $porcoes > 10000) {
            throw new InvalidArgumentException('O rendimento deve ser um número entre 1 e 10000 porções.');
        }

        $ratingValue = $entrada['rating'] ?? null;
        if (!is_numeric($ratingValue) || !is_finite((float) $ratingValue)
            || (float) $ratingValue < 0 || (float) $ratingValue > 5
            || abs(((float) $ratingValue * 2) - round((float) $ratingValue * 2)) > 0.000001) {
            throw new InvalidArgumentException('A nota deve estar entre 0 e 5, em intervalos de 0,5.');
        }

        $recipeId = null;
        if (isset($entrada['id']) && $entrada['id'] !== '') {
            $recipeId = filter_var($entrada['id'], FILTER_VALIDATE_INT);
            if ($recipeId === false || $recipeId < 1) {
                throw new InvalidArgumentException('O identificador da receita é inválido.');
            }
        }

        $dados = [
            'id' => $recipeId,
            'n' => $nome,
            'c' => $categoria,
            't' => $tempo,
            'p' => $porcoes,
            'rating' => (float) $ratingValue,
            'desc' => $this->texto($entrada['desc'] ?? '', 'descrição', 400),
            'note' => $this->texto($entrada['note'] ?? '', 'observações', 400),
            'ingredients' => $this->ingredientes($entrada['ingredients'] ?? []),
            'steps' => $this->etapas($entrada['steps'] ?? []),
        ];

        $savedId = $this->model->salvar($usuarioId, $dados);
        $recipe = $this->model->buscarPorId($usuarioId, $savedId);
        if ($recipe === null) {
            throw new RuntimeException('A receita foi salva, mas não pôde ser carregada.');
        }

        return $recipe;
    }

    public function alternarFavorita(int $usuarioId, array $entrada): array
    {
        $recipe = $this->model->alternarFavorita($usuarioId, $this->id($entrada));
        if ($recipe === null) {
            throw new OutOfBoundsException('A receita não foi encontrada.');
        }

        return $recipe;
    }

    public function compartilhar(int $usuarioId, array $entrada): array
    {
        $recipe = $this->model->marcarCompartilhada($usuarioId, $this->id($entrada));
        if ($recipe === null) {
            throw new OutOfBoundsException('A receita não foi encontrada.');
        }

        return $recipe;
    }

    public function marcarIngrediente(int $usuarioId, array $entrada): void
    {
        $recipeId = $this->id($entrada);
        $position = filter_var($entrada['index'] ?? null, FILTER_VALIDATE_INT);
        if ($position === false || $position < 0 || !is_bool($entrada['checked'] ?? null)) {
            throw new InvalidArgumentException('Os dados do ingrediente são inválidos.');
        }
        if (!$this->model->atualizarIngrediente($usuarioId, $recipeId, $position, $entrada['checked'])) {
            throw new OutOfBoundsException('O ingrediente não foi encontrado.');
        }
    }

    public function excluir(int $usuarioId, array $entrada): void
    {
        if (!$this->model->excluir($usuarioId, $this->id($entrada))) {
            throw new OutOfBoundsException('A receita não foi encontrada.');
        }
    }

    private function id(array $entrada): int
    {
        $id = filter_var($entrada['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('O identificador da receita é inválido.');
        }

        return $id;
    }

    private function texto(mixed $value, string $field, int $maxLength, bool $required = false): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('O campo ' . $field . ' é inválido.');
        }
        $value = trim($value);
        $length = mb_strlen($value);
        if (($required && $length === 0) || $length > $maxLength) {
            throw new InvalidArgumentException('Confira o campo ' . $field . '.');
        }

        return $value;
    }

    private function ingredientes(mixed $items): array
    {
        if (!is_array($items) || count($items) > 100) {
            throw new InvalidArgumentException('A lista de ingredientes é inválida.');
        }

        $ingredients = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item[0], $item[1])
                || !is_string($item[0]) || !is_string($item[1])) {
                throw new InvalidArgumentException('Confira os ingredientes informados.');
            }
            $name = trim($item[0]);
            $quantity = trim($item[1]);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 150 || mb_strlen($quantity) > 100) {
                throw new InvalidArgumentException('O ingrediente ou a quantidade excede o limite permitido.');
            }
            $ingredients[] = [$name, $quantity];
        }

        return $ingredients;
    }

    private function etapas(mixed $items): array
    {
        if (!is_array($items) || count($items) > 100) {
            throw new InvalidArgumentException('A lista de etapas é inválida.');
        }

        $steps = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException('Confira as etapas informadas.');
            }
            $step = trim($item);
            if ($step === '') {
                continue;
            }
            if (mb_strlen($step) > 2000) {
                throw new InvalidArgumentException('Cada etapa pode ter até 2000 caracteres.');
            }
            $steps[] = $step;
        }

        return $steps;
    }
}

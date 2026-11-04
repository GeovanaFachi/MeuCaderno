<?php

class ReceitaModel
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarTodasPorUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nome, categoria, tempo_minutos, porcoes, nota, descricao, observacoes,
                    favorita, compartilhada,
                    DATE_FORMAT(criado_em, '%Y-%m-%dT%H:%i:%s') AS criado_em
             FROM receitas
             WHERE usuario_id = :usuario_id
             ORDER BY criado_em DESC, id DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $rows = $stmt->fetchAll();
        $recipes = [];
        $ids = [];

        foreach ($rows as $index => $row) {
            $id = (int) $row['id'];
            $ids[] = $id;
            $recipes[$id] = [
                'id' => $id,
                'n' => $row['nome'],
                'c' => $row['categoria'],
                't' => (int) $row['tempo_minutos'],
                'p' => (int) $row['porcoes'],
                'rating' => (float) $row['nota'],
                'desc' => $row['descricao'],
                'note' => $row['observacoes'],
                'f' => (bool) $row['favorita'],
                'shared' => (bool) $row['compartilhada'],
                'createdAt' => $row['criado_em'],
                'bg' => ['#FBE3CC', '#E3F0D5', '#EAF4DD', '#FFF6E3', '#FDEBD6', '#F6EBDD', '#FFF1D6', '#FBE4DC'][$index % 8],
                'ingredients' => [],
                'steps' => [],
                'checkedIngredients' => [],
            ];
        }

        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT receita_id, nome, quantidade, posicao, separado
             FROM ingredientes
             WHERE receita_id IN ({$placeholders})
             ORDER BY receita_id, posicao"
        );
        $stmt->execute($ids);

        foreach ($stmt->fetchAll() as $ingredient) {
            $recipeId = (int) $ingredient['receita_id'];
            $index = count($recipes[$recipeId]['ingredients']);
            $recipes[$recipeId]['ingredients'][] = [
                $ingredient['nome'],
                $ingredient['quantidade'],
            ];
            if ($ingredient['separado']) {
                $recipes[$recipeId]['checkedIngredients'][] = $index;
            }
        }

        $stmt = $this->db->prepare(
            "SELECT receita_id, descricao
             FROM etapas
             WHERE receita_id IN ({$placeholders})
             ORDER BY receita_id, posicao"
        );
        $stmt->execute($ids);

        foreach ($stmt->fetchAll() as $step) {
            $recipes[(int) $step['receita_id']]['steps'][] = $step['descricao'];
        }

        return array_values($recipes);
    }

    public function salvar(int $usuarioId, array $dados): int
    {
        $this->db->beginTransaction();

        try {
            if ($dados['id'] !== null) {
                $stmt = $this->db->prepare(
                    'SELECT id FROM receitas WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
                );
                $stmt->execute(['id' => $dados['id'], 'usuario_id' => $usuarioId]);
                if (!$stmt->fetch()) {
                    throw new OutOfBoundsException('A receita não foi encontrada.');
                }

                $stmt = $this->db->prepare(
                    'UPDATE receitas
                     SET nome = :nome, categoria = :categoria, tempo_minutos = :tempo,
                         porcoes = :porcoes, nota = :nota, descricao = :descricao,
                         observacoes = :observacoes
                     WHERE id = :id AND usuario_id = :usuario_id'
                );
                $stmt->execute([
                    'nome' => $dados['n'],
                    'categoria' => $dados['c'],
                    'tempo' => $dados['t'],
                    'porcoes' => $dados['p'],
                    'nota' => $dados['rating'],
                    'descricao' => $dados['desc'],
                    'observacoes' => $dados['note'],
                    'id' => $dados['id'],
                    'usuario_id' => $usuarioId,
                ]);
                $receitaId = $dados['id'];

                $stmt = $this->db->prepare('DELETE FROM ingredientes WHERE receita_id = :id');
                $stmt->execute(['id' => $receitaId]);
                $stmt = $this->db->prepare('DELETE FROM etapas WHERE receita_id = :id');
                $stmt->execute(['id' => $receitaId]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO receitas
                     (usuario_id, nome, categoria, tempo_minutos, porcoes, nota, descricao, observacoes)
                     VALUES (:usuario_id, :nome, :categoria, :tempo, :porcoes, :nota, :descricao, :observacoes)'
                );
                $stmt->execute([
                    'usuario_id' => $usuarioId,
                    'nome' => $dados['n'],
                    'categoria' => $dados['c'],
                    'tempo' => $dados['t'],
                    'porcoes' => $dados['p'],
                    'nota' => $dados['rating'],
                    'descricao' => $dados['desc'],
                    'observacoes' => $dados['note'],
                ]);
                $receitaId = (int) $this->db->lastInsertId();
            }

            $ingredientStmt = $this->db->prepare(
                'INSERT INTO ingredientes (receita_id, nome, quantidade, posicao)
                 VALUES (:receita_id, :nome, :quantidade, :posicao)'
            );
            foreach ($dados['ingredients'] as $index => $ingredient) {
                $ingredientStmt->execute([
                    'receita_id' => $receitaId,
                    'nome' => $ingredient[0],
                    'quantidade' => $ingredient[1],
                    'posicao' => $index,
                ]);
            }

            $stepStmt = $this->db->prepare(
                'INSERT INTO etapas (receita_id, descricao, posicao)
                 VALUES (:receita_id, :descricao, :posicao)'
            );
            foreach ($dados['steps'] as $index => $step) {
                $stepStmt->execute([
                    'receita_id' => $receitaId,
                    'descricao' => $step,
                    'posicao' => $index,
                ]);
            }

            $this->db->commit();
            return $receitaId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function buscarPorId(int $usuarioId, int $receitaId): ?array
    {
        foreach ($this->buscarTodasPorUsuario($usuarioId) as $recipe) {
            if ($recipe['id'] === $receitaId) {
                return $recipe;
            }
        }

        return null;
    }

    public function alternarFavorita(int $usuarioId, int $receitaId): ?array
    {
        $recipe = $this->buscarPorId($usuarioId, $receitaId);
        if ($recipe === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE receitas SET favorita = :favorita WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute([
            'favorita' => $recipe['f'] ? 0 : 1,
            'id' => $receitaId,
            'usuario_id' => $usuarioId,
        ]);

        return $this->buscarPorId($usuarioId, $receitaId);
    }

    public function marcarCompartilhada(int $usuarioId, int $receitaId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE receitas SET compartilhada = 1 WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['id' => $receitaId, 'usuario_id' => $usuarioId]);

        return $this->buscarPorId($usuarioId, $receitaId);
    }

    public function atualizarIngrediente(int $usuarioId, int $receitaId, int $posicao, bool $separado): bool
    {
        $stmt = $this->db->prepare(
            'SELECT i.id
             FROM ingredientes i
             INNER JOIN receitas r ON r.id = i.receita_id
             WHERE r.id = :receita_id AND r.usuario_id = :usuario_id AND i.posicao = :posicao'
        );
        $stmt->execute([
            'receita_id' => $receitaId,
            'usuario_id' => $usuarioId,
            'posicao' => $posicao,
        ]);
        $ingredientId = $stmt->fetchColumn();

        if ($ingredientId === false) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE ingredientes SET separado = :separado WHERE id = :id');
        $stmt->execute(['separado' => $separado ? 1 : 0, 'id' => $ingredientId]);
        return true;
    }

    public function excluir(int $usuarioId, int $receitaId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM receitas WHERE id = :id AND usuario_id = :usuario_id');
        $stmt->execute(['id' => $receitaId, 'usuario_id' => $usuarioId]);

        return $stmt->rowCount() > 0;
    }
}

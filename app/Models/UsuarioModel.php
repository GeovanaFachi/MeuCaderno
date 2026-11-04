<?php

class UsuarioModel
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, email, senha FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function criar(string $nome, string $email, string $senha): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)'
        );
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => password_hash($senha, PASSWORD_DEFAULT),
        ]);

        return (int) $this->db->lastInsertId();
    }
}

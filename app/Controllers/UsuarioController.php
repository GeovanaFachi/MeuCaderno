<?php

require_once __DIR__ . '/../Models/UsuarioModel.php';

class UsuarioController
{
    public function __construct(private UsuarioModel $model)
    {
    }

    public function autenticar(string $email, string $senha): bool
    {
        $usuario = $this->model->buscarPorEmail(mb_strtolower(trim($email)));
        if ($usuario === null || !password_verify($senha, $usuario['senha'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($usuario['senha']);
        $_SESSION['usuario'] = $usuario;
        return true;
    }

    public function cadastrar(string $nome, string $email, string $senha): void
    {
        $nome = trim($nome);
        $email = mb_strtolower(trim($email));

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
            throw new InvalidArgumentException('Informe um nome com até 100 caracteres.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        if (strlen($senha) < 8 || strlen($senha) > 255) {
            throw new InvalidArgumentException('A senha deve ter entre 8 e 255 caracteres.');
        }

        $userId = $this->model->criar($nome, $email, $senha);
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => $userId,
            'nome' => $nome,
            'email' => $email,
        ];
    }
}

CREATE DATABASE IF NOT EXISTS db_meu_caderno
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_meu_caderno;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receitas (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredientes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etapas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receita_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(2000) NOT NULL,
    posicao SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_etapas_receita_posicao (receita_id, posicao),
    CONSTRAINT fk_etapas_receita
        FOREIGN KEY (receita_id) REFERENCES receitas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

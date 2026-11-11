# Testes end to end

## Objetivo e escopo

Esta etapa inicia o servidor PHP local e percorre a aplicação publicada em
`public/` por HTTP, preservando cookies e sessão. Exercita páginas HTML,
formulários, cadastro, CSRF, respostas JSON da API usada pela interface,
persistência de receita e logout. A automação é feita com PHP e cURL, sem
instalar dependências de navegador.

## Proteção do banco de dados

O executor usa exclusivamente `MEUCADERNO_TEST_DB_*`; não usa o banco padrão
da aplicação nem `config/conexao.php`. O banco precisa ter nome terminado em
`_test`, por exemplo `db_meu_caderno_test`, e o schema da aplicação precisa
estar instalado nele. Os testes criam um e-mail de usuário aleatório e removem
esse usuário ao final (as receitas relacionadas são removidas pela chave
estrangeira). Use uma instância de teste isolada.

Para criar o schema, crie o banco de teste e execute nele somente os comandos
`CREATE TABLE` de `database/Script.sql`. Não execute as instruções
`CREATE DATABASE` ou `USE` desse arquivo; confira cuidadosamente o banco
selecionado antes de executar qualquer SQL.

## Requisitos

- PHP CLI 8.1 ou superior, com extensões `pdo_mysql`, `curl`, `dom`, `mbstring`
  e `session`.
- MySQL/MariaDB com schema carregado em um banco dedicado terminado em `_test`.
- Permissões de conexão para ler e excluir o usuário criado pelos testes.
- Porta local disponível em `127.0.0.1`.

## Execução no PowerShell

Na raiz do projeto:

```powershell
$env:MEUCADERNO_TEST_DB_HOST = 'localhost'
$env:MEUCADERNO_TEST_DB_NAME = 'db_meu_caderno_test'
$env:MEUCADERNO_TEST_DB_USER = 'usuario_de_teste'
$env:MEUCADERNO_TEST_DB_PASSWORD = 'senha_de_teste'
# Opcional, se o servidor usar uma porta diferente:
$env:MEUCADERNO_TEST_DB_PORT = '3306'

php tests/e2e/run.php
```

Defina `MEUCADERNO_TEST_DB_PASSWORD` como string vazia quando o usuário não
tiver senha. O executor configura o servidor local com essas credenciais,
inicia-o em uma porta disponível e o encerra ao final. Retorna `0` se os nove
casos passarem; configuração inválida ou falhas retornam código diferente de
zero.

## Fluxos cobertos

1. Redirecionamento de visitante e renderização do formulário de cadastro.
2. Token CSRF no formulário e rejeição de confirmação de senha divergente.
3. Cadastro, login e acesso à página inicial com sessão autenticada.
4. Rejeição de chamada à API com CSRF inválido.
5. Criação e renderização de receita, ingredientes e etapas.
6. Favoritar, compartilhar e marcar ingrediente através da API.
7. Respostas HTTP para dados inválidos e receita inexistente.
8. Exclusão da receita e logout com CSRF.
9. Redirecionamento para login após o fim da sessão.

## Limitações

A suíte exercita o front controller e a API por HTTP; ela não controla um
navegador gráfico nem valida layout, acessibilidade visual ou interação real
com elementos DOM. Esses aspectos exigem uma etapa de automação de navegador.

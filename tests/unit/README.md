# Testes unitários

## Objetivo e escopo

Esta etapa verifica as regras isoladas de `UsuarioController` e `ReceitaController`.
Os testes substituem os modelos por doubles em memória, sem conexão com MySQL,
requisições HTTP, templates ou execução do front controller. A integração dos
controladores com os modelos e o MySQL é coberta separadamente em
[`../integration/README.md`](../integration/README.md). O front controller
`public/index.php`, as rotas HTTP e a interface permanecem fora do escopo
unitário e serão avaliados na etapa end to end.

## Requisitos

- PHP CLI 8.1 ou superior.
- Extensões `mbstring` e `session`.
- Nenhum banco de dados ou pacote de terceiros.

## Execução

Na raiz do projeto:

```powershell
php tests/unit/run.php
```

O comando imprime cada caso como `PASS` ou `FAIL` e retorna código de saída
diferente de zero quando houver falha.

## Casos cobertos

### `ReceitaController`

- Encaminhamento da listagem ao modelo.
- Criação/edição e normalização de textos, ingredientes e etapas.
- Categorias permitidas e validação de campos obrigatórios.
- Limites de tempo e rendimento.
- Notas de 0 a 5 em intervalos de 0,5.
- Identificadores, limites e formatos de ingredientes e etapas.
- Falha ao recarregar receita salva.
- Favoritar, compartilhar, marcar ingrediente e excluir, incluindo itens ausentes.

### `UsuarioController`

- Autenticação com e-mail normalizado, senha válida, usuário inexistente e senha incorreta.
- Cadastro com dados normalizados e sessão sem a senha.
- Validação de nome, e-mail e comprimento da senha.

## Critério de aprovação

Todos os casos devem passar e o processo deve terminar com código `0`.
Esses testes não comprovam consultas SQL, persistência, respostas HTTP ou fluxo
real de sessão/cookies; esses comportamentos não fazem parte do escopo unitário.

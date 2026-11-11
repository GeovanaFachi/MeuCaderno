# Testes de integração

## Objetivo e escopo

Esta etapa integra `UsuarioController` e `ReceitaController` com os modelos
reais e um banco MySQL/MariaDB real. Verifica gravação e leitura de dados,
atualizações, transações, autenticação/cadastro, isolamento por proprietário
e exclusão dos registros relacionados. Não inicia um servidor HTTP ou navegador;
os fluxos de interface e roteamento serão cobertos pelos testes end to end.

## Proteção do banco de dados

O executor **não usa** `MEUCADERNO_DB_*`, as credenciais padrão da aplicação ou
`config/conexao.php`. Configure as variáveis `MEUCADERNO_TEST_DB_*` para um banco
dedicado cujo nome termine em `_test` (por exemplo, `db_meu_caderno_test`).
Nomes que não atendem a essa regra são recusados antes de qualquer alteração.

O executor cria somente tabelas ausentes com `CREATE TABLE IF NOT EXISTS`;
não apaga nem esvazia tabelas. Os usuários de teste são identificados por IDs
criados na execução e removidos ao final, inclusive se algum caso falhar.
Ainda assim, use um banco isolado e um usuário MySQL com permissões limitadas
àquele banco.

## Requisitos

- PHP CLI 8.1 ou superior, com `pdo_mysql`, `mbstring` e `session`.
- MySQL/MariaDB acessível e um banco de teste previamente criado.
- Credenciais de teste com permissão para criar tabelas e inserir, consultar,
  atualizar e excluir os próprios registros.

Exemplo de criação do banco (execute no servidor MySQL/MariaDB):

```sql
CREATE DATABASE db_meu_caderno_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

## Execução no PowerShell

Na raiz do projeto, configure as credenciais do banco dedicado:

```powershell
$env:MEUCADERNO_TEST_DB_HOST = 'localhost'
$env:MEUCADERNO_TEST_DB_NAME = 'db_meu_caderno_test'
$env:MEUCADERNO_TEST_DB_USER = 'usuario_de_teste'
$env:MEUCADERNO_TEST_DB_PASSWORD = 'senha_de_teste'
# Opcional, se o servidor usar uma porta diferente:
$env:MEUCADERNO_TEST_DB_PORT = '3306'

php tests/integration/run.php
```

Defina `MEUCADERNO_TEST_DB_PASSWORD` como string vazia quando o usuário não
tiver senha. O comando imprime cada caso como `PASS` ou `FAIL`; retorna `0`
quando os seis casos passam e código diferente de zero em caso de falha ou
configuração inválida.

## Casos cobertos

1. Cadastro de usuário, hash da senha e dados de sessão.
2. Criação e leitura de receita com ingredientes e etapas.
3. Edição de receita e substituição de ingredientes e etapas.
4. Persistência de favorito, compartilhamento e estado de ingrediente.
5. Isolamento de leitura, alteração e exclusão entre usuários.
6. Exclusão de receita e remoção dos registros dependentes.

## Limitações

Os testes verificam controllers e modelos contra o banco. Eles não exercitam
requisições HTTP, cookies, CSRF, renderização HTML, navegador ou rotas Apache;
esses itens pertencem à etapa end to end.

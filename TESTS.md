# TESTS.md — Testes automatizados do `local_relationship`

Este arquivo descreve os testes do plugin e como executá-los via Docker.

> Para detalhes do mecanismo (como os scripts funcionam, troubleshooting, e como replicar este formato em outros plugins) veja `DOCKER_TESTS.md`.

---

## Pré-requisitos

- Usuário no grupo `docker` (`sudo usermod -aG docker $USER` + nova sessão).
- Stack Docker em `/home/$USER/workspace/docker/php56-nginx/` com `docker-compose.yml` definindo o serviço `moodle-local-unasuscp`.
- `.env` do stack (`../../../../.env`) com `BEHAT_PREFIX=bht_`.
- `.env` local deste plugin (já versionado) com `CORE_NAME`, `DOCKER_VERSION`, `URL_NAME`, `PLUGIN_COMPONENT`, `PLUGIN_PATH`, `PLUGIN_TAG`.

---

## Testes Behat (integração)

**Driver:** Selenium Chrome Standalone (`selenium/standalone-chrome:3.141.59-selenium`, Chrome 75)
**Container Moodle:** `moodle-local-unasuscp`
**Container Selenium:** `selenium-chrome-unasuscp` (compartilhado entre plugins do mesmo `CORE_NAME`)
**URL base:** `http://local-unasus-cp.moodle.ufsc.br`
**Tag padrão:** `@local_relationship`

### Features atuais

- `tests/behat/relationship.feature` (11 cenários) — capability checks, navegação para o link, CRUD básico de relationship, edit/cancel, validação do nome obrigatório, cancel button preserva listagem, busca filtra por nome, relationship com component != null esconde edit/delete, **paginação >25 com nomes zero-padded**, **link "Listar" expande painel "Cursos usando..." quando o relationship tem enrol instance**.
- `tests/behat/relationship_cohort_groups.feature` (15 cenários) — CRUD de cohort e group, toggle `allowdupsingroups`, toggle `uniformdistribution` no group e no cohort, transferência de membro ao remover cohort, bloqueio de delete quando há cohort associado, 2 cohorts mesmo role, userlimit no group, botão "Distribuir remanescentes" ativando distribuição uniforme, **mensagem "Não há coortes disponíveis"**, **estado inicial da página de grupos**.
- `tests/behat/assign.feature` (3 cenários) — adição manual de membro via dual selector, remoção manual, view read-only para usuário sem capability `:assign`.
- `tests/behat/autogroup.feature` (4 cenários) — geração por número com token `#` (série numérica), por número com token `@` (série de letras A→B→C), por cohort com naming `@`/`#` em modo `value_is_a_name`, detecção de colisão no preview ("(Já existente)" em vermelho) com criação parcial dos não-conflitantes.

**Total Behat:** 33 cenários, 1074 steps, ~20-25 min de execução em Selenium.

### Step custom

`tests/behat/behat_relationship.php` define quatro steps:
- `When I click on the element with xpath "..."` — atalho para click via XPath puro.
- `Given the relationship "X" has component "Y"` — marca um relationship como gerenciado por outro componente Moodle (atualiza `relationship.component` no DB), permitindo testar a branch "cantedit" de `index.php`/`edit.php`/`edit_cohort.php`/`edit_group.php` sem instalar um plugin que efetivamente "possua" o relationship.
- `Given N relationships exist in category "X" with prefix "Y"` — bulk-create por insert direto no DB. Nomes são zero-padded para que ordenação alfabética seja igual à numérica. Usado para empurrar a listagem além das 25 linhas por página.
- `Given course "X" uses the relationship "Y"` — insere uma linha em `{enrol}` com `enrol='relationship'` e `customint1=relationshipid`. Permite testar o branch "Cursos usando..." em `index.php` sem o plugin `enrol_relationship` instalado.

Step custom: `tests/behat/behat_relationship.php` (estende `behat_base`) — adiciona steps como `I go to "<URL>"`, `I click on the element with xpath "..."`, etc., e o setup `the following "relationships" exist`.

### Como executar

```bash
# Todos os cenários com tag @local_relationship
./run_behat.sh

# Um feature file específico
./run_behat.sh tests/behat/relationship.feature
./run_behat.sh tests/behat/relationship_cohort_groups.feature

# Filtrar por tag arbitrária
./run_behat.sh --tags=@javascript

# Filtrar por nome do cenário
./run_behat.sh --name="texto do Scenario:"

# Forçar reinicialização do ambiente Behat (após mudar config.php manualmente)
./run_behat.sh --init

# Encerrar containers ao terminar
./stop_behat.sh           # para Moodle + Selenium
./stop_behat.sh --down    # também faz docker compose down
```

---

## Testes PHPUnit (unitários)

**Cobertura atual: 73 testes, 142 asserções, ~8s combinados.**

### Arquivos

- `tests/parse_name_test.php` — 18 testes para `relationship_groups_parse_name` (parser `@` letra / `#` número). Cobre: série A→Z→AA, formato sem token, ambos tokens (precedência de `@`), `value_is_a_name=true`, tokens em diferentes posições, múltiplas ocorrências, formato vazio.
- `tests/crud_test.php` — 36 testes para `lib.php`. Cobre CRUD completo:
  - Múltiplos cohorts no mesmo papel (e múltiplos papéis cada um com múltiplos cohorts) coexistem no mesmo relationship — afirmação explícita de que não há unique em (relationshipid, roleid).
  - `relationship`: defaults de campos opcionais, exceção em nome ausente, trim de nome, evento `relationship_created`/`updated`/`deleted`, get com tags, delete com cascata de groups, delete bloqueado por cohorts existentes (retorno `-1`).
  - `relationship_cohorts`: timestamps automáticos, atualização de `timemodified`, get com cohort e role embarcadas, `role_name=false` quando role inexistente, listagem filtrada por relationship, **delete com transferência de members para candidate cohort** (mesmo role e cohort com o user), delete removendo member quando não há candidato com o user, descarte de duplicata quando target já tem o member, candidate ignorado quando role difere.
  - `relationship_groups`: trim de nome, exceção em relationship inexistente, evento `relationshipgroup_created`/`updated`/`deleted`, get com `size` (contagem de membros), delete em cascata sobre members.
  - `relationship_members`: insert simples, **duplicata na tupla (group, cohort, user) retorna false sem erro**, FK inválida lança exceção, evento `relationshipgroup_member_added` com `relateduserid`/`objectid` corretos, gotcha documentado: `remove_member` retorna `true` e dispara evento **mesmo quando o registro não existia** (consequência do `$DB->delete_records` ser permissivo).
- `tests/distribution_test.php` — 11 testes para `relationship_uniformly_distribute_users` (algoritmo greedy). Cobre: array vazio sem efeitos colaterais, sem grupos no pool, grupos sem flag `uniformdistribution` ignorados, grupo único ilimitado absorve todos, 2 grupos ilimitados dividem par, ímpar joga extra no primeiro (desempate por id), `userlimit` corta tamanho, excedente descartado quando todos cheios, `userlimit=0` significa ilimitado em meio a grupos limitados, contagem inicial não-zero (grupo pré-populado) ajusta distribuição, idempotência (rodar duas vezes com mesmos usuários não cria duplicatas).
- `tests/observer_test.php` — 8 testes para `classes/observer.php` e `local_relationship_cron`. Dispara eventos core (`cohort_add_member`, `cohort_remove_member`, `cohort_delete_cohort`) via API do Moodle e verifica os efeitos: distribuição uniforme quando cohort tem `uniformdistribution=1`, no-op quando flag desligada, no-op quando cohort não está ligado ao relationship, cascata de remoção quando user é removido do cohort, isolamento entre users, smoke test do cron resgatando membros não distribuídos, **handler de `cohort_deleted` derrubando os `relationship_cohorts` órfãos** (regressão do fix em `db/events.php`), e cascata quando o mesmo cohort está ligado a múltiplos relationships.

### Como executar

```bash
# Todos os arquivos *_test.php em tests/
./run_tests.sh

# Um arquivo específico
./run_tests.sh tests/parse_name_test.php
./run_tests.sh tests/crud_test.php
./run_tests.sh tests/distribution_test.php

# Reset completo das tabelas PHPUnit
./run_tests.sh --reset
```

### Convenções para novos testes

- Cada arquivo: `tests/<assunto>_test.php`, classe `local_relationship_<assunto>_testcase` estendendo `advanced_testcase`.
- Anotar com `@group local_relationship` para facilitar filtragem.
- Usar `$this->resetAfterTest()` no `setUp` quando o teste mexer em dados.
- Sintaxe restrita a PHP 5.6: `array()` em vez de `[]`, sem type hints em scalars, sem `??`, sem `?:`.
- `lib.php` usa `tag_set()` mas não requer `tag/lib.php` diretamente — sempre incluir `require_once($CFG->dirroot . '/tag/lib.php');` no topo de um test que carrega só `lib.php`, ou incluir `locallib.php` (que já carrega taglib transitivamente).

---

## Troubleshooting rápido

- **Behat falha com "Selenium not reachable"** — verifique a imagem do container Selenium: tem que ser `3.141.59`. Não atualizar. O script recria automaticamente se a imagem estiver diferente.
- **Behat init reclama de "upgraderunning"** — o `run_behat.sh --init` detecta e limpa esse lock automaticamente.
- **PHP errors de memória** — o script já usa `-d memory_limit=512M` para o `init.php`; para o Behat em si, ajuste `PHP_MEMORY_LIMIT` no `.env` do stack docker.
- **Front page do Behat retorna 301/HTTPS** — significa que `/etc/hosts` no Moodle container não está apontando `URL_NAME` para `127.0.0.1`. O script faz esse mapeamento; se você suspeitar de problema, rode `docker exec -u 0 moodle-local-unasuscp cat /etc/hosts`.

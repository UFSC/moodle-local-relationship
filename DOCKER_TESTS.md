# DOCKER_TESTS.md — Formato reusável para rodar PHPUnit e Behat de plugins Moodle via Docker

Guia autocontido para instalar a mesma infraestrutura de testes em qualquer plugin Moodle hospedado no stack Docker da UFSC (`/home/$USER/workspace/docker/<DOCKER_VERSION>/`).

**Para replicar em outro plugin:** copie este arquivo, siga o passo-a-passo, e ajuste o `.env`. Os três scripts shell são idênticos entre plugins — toda a especificidade vai no `.env`.

---

## Sumário

1. [Visão geral](#visão-geral)
2. [Pré-requisitos](#pré-requisitos)
3. [Passo-a-passo de instalação](#passo-a-passo-de-instalação)
4. [Como usar](#como-usar)
5. [Como funciona (arquitetura)](#como-funciona-arquitetura)
6. [Troubleshooting](#troubleshooting)
7. [Adaptação para outros stacks](#adaptação-para-outros-stacks)

---

## Visão geral

Esta infraestrutura entrega três scripts shell na **raiz do plugin** que automatizam:

- **`run_tests.sh`** — testes **PHPUnit** dentro do container Moodle: instala Composer 1.x, extensões PHP, dependências do Moodle (`composer install`), configura `phpunit_*` no `config.php`, roda `admin/tool/phpunit/cli/init.php`, e executa `vendor/bin/phpunit` em cada `tests/*_test.php`.
- **`run_behat.sh`** — testes **Behat** com Selenium Chrome: sobe os containers (Moodle + `selenium/standalone-chrome:3.141.59`), configura `/etc/hosts` em ambos para resolver `URL_NAME`, instala wrapper do Composer 2.2.21 (necessário para `init.php` do Behat em PHP 5.6), configura `behat_*` + `behat_config` no `config.php`, aplica auto-correções de configurações desatualizadas, e executa `vendor/bin/behat` (com `trap` para desabilitar modo Behat ao sair).
- **`stop_behat.sh`** — para os containers Moodle e Selenium; com `--down` também executa `docker compose down`.

Toda a especificidade do plugin (frankenstyle, caminho, tag Behat padrão) vive no `.env` local. Já as variáveis do stack Docker (versão, prefixo Behat) vivem no `.env` do diretório docker-compose. Os scripts leem os dois.

**Hierarquia de leitura do `.env`:**

```
<plugin>/.env                                 → CORE_NAME, DOCKER_VERSION, URL_NAME,
                                                PLUGIN_COMPONENT, PLUGIN_PATH, PLUGIN_TAG
<docker-stack>/.env  (../../../../.env)      → BEHAT_PREFIX, PHP_MEMORY_LIMIT, etc.
```

---

## Pré-requisitos

- **Usuário no grupo `docker`** (os scripts não usam `sudo`):
  ```bash
  sudo usermod -aG docker $USER
  # logout/login obrigatório
  ```
- **Stack Docker existente** em `/home/$USER/workspace/docker/<DOCKER_VERSION>/` com `docker-compose.yml` declarando o serviço `moodle-local-<CORE_NAME>` que monta o webroot em `/home/moodle/www/local-<CORE_NAME>/` e o `moodledata` em `/home/moodle/moodledata/`.
- **`.env` do stack** com pelo menos:
  ```
  BEHAT_PREFIX=bht_
  PHP_MEMORY_LIMIT=2048M
  ```
- **Rede Docker** `moodle-network-<DOCKER_VERSION>` (criada automaticamente pelo `run_behat.sh` se não existir).
- **Plugin instalado** no Moodle (`<moodle-root>/<PLUGIN_PATH>/`) e checado in via git.

---

## Passo-a-passo de instalação

### Passo 1 — `.env` (criar na raiz do plugin)

```ini
CORE_NAME=unasuscp
DOCKER_VERSION=php56-nginx
URL_NAME=local-unasus-cp.moodle.ufsc.br
PLUGIN_COMPONENT=frankenstyle_aqui
PLUGIN_PATH=tipo/subdir
PLUGIN_TAG=@frankenstyle_aqui
```

Variáveis:

| Variável | O que é | Exemplo |
|---|---|---|
| `CORE_NAME` | Nome curto do site Moodle (parte depois de `local-`). Decide o nome do container Moodle (`moodle-local-$CORE_NAME`) e Selenium (`selenium-chrome-$CORE_NAME`). | `unasuscp` |
| `DOCKER_VERSION` | Diretório do stack (`/home/$USER/workspace/docker/$DOCKER_VERSION/`). Decide a rede (`moodle-network-$DOCKER_VERSION`). | `php56-nginx` |
| `URL_NAME` | Domínio externo do site Moodle. Usado em `behat_wwwroot` e nos `/etc/hosts` injetados nos containers. | `local-unasus-cp.moodle.ufsc.br` |
| `PLUGIN_COMPONENT` | Frankenstyle do plugin (`tipo_subdir`). | `local_relationship`, `report_unasus`, `mod_meu` |
| `PLUGIN_PATH` | Caminho do plugin relativo ao Moodle root. | `local/relationship`, `report/unasus`, `mod/meu` |
| `PLUGIN_TAG` | (opcional) Tag Behat usada quando `run_behat.sh` roda sem argumentos. Default: `@$PLUGIN_COMPONENT`. | `@local_relationship` |

> **Dica:** versione `.env.template` (sem valores reais) e adicione `.env` ao `.gitignore` se preferir, ou versione o `.env` se os valores forem genéricos da equipe.

### Passo 2 — `run_tests.sh`

Salve o conteúdo abaixo em `<plugin>/run_tests.sh` e marque como executável:

```bash
chmod +x run_tests.sh
```

```bash
#!/bin/bash
# =============================================================================
# run_tests.sh - Executa testes PHPUnit do plugin via Docker
#
# Uso:
#   ./run_tests.sh                  # Roda todos os testes do plugin
#   ./run_tests.sh <arquivo>        # Roda um arquivo de teste específico
#   ./run_tests.sh --reset          # Reinicializa as tabelas PHPUnit
#
# Configuração (via .env):
#   CORE_NAME, DOCKER_VERSION, URL_NAME, PLUGIN_COMPONENT, PLUGIN_PATH
# =============================================================================

set -e

log()  { echo -e "\033[0;32m[INFO]\033[0m  $*"; }
warn() { echo -e "\033[0;33m[WARN]\033[0m  $*"; }
err()  { echo -e "\033[0;31m[ERROR]\033[0m $*" >&2; exit 1; }

if [ -f ".env" ]; then
  while read -r line || [[ -n "$line" ]]; do
    if [[ ! "$line" =~ ^# && -n "$line" ]]; then
      export "$line"
    fi
  done < .env
else
  err "Arquivo .env não encontrado."
fi

[ -n "$CORE_NAME" ]        || err "Variável CORE_NAME ausente no .env."
[ -n "$DOCKER_VERSION" ]   || err "Variável DOCKER_VERSION ausente no .env."
[ -n "$PLUGIN_COMPONENT" ] || err "Variável PLUGIN_COMPONENT ausente no .env (ex.: local_relationship)."
[ -n "$PLUGIN_PATH" ]      || err "Variável PLUGIN_PATH ausente no .env (ex.: local/relationship)."

SISTEM_NAME="local-$CORE_NAME"
CONTAINER_NAME="moodle-$SISTEM_NAME"
DOCKER_COMPOSE_DIR="/home/$USER/workspace/docker/$DOCKER_VERSION"
MOODLE_LOCAL_SITE="www/$SISTEM_NAME"
MOODLE_ROOT_IN_CONTAINER="/home/moodle/$MOODLE_LOCAL_SITE"
PHPUNIT_PREFIX="phpu_"
PHPUNIT_DATAROOT="/home/moodle/moodledata/${PHPUNIT_PREFIX}$SISTEM_NAME"

RESET_FLAG=""
TEST_FILE=""
for arg in "$@"; do
    case "$arg" in
        --reset) RESET_FLAG="yes" ;;
        *)       TEST_FILE="$arg" ;;
    esac
done

container_is_running() {
    docker inspect -f '{{.State.Running}}' "$CONTAINER_NAME" 2>/dev/null | grep -q "true"
}

exec_as_root() {
    docker exec -e XDEBUG_MODE=off "$CONTAINER_NAME" bash -c "$1"
}

exec_as_moodle() {
    docker exec -e XDEBUG_MODE=off -u moodle "$CONTAINER_NAME" bash -c "$1"
}

get_moodle_build() {
    exec_as_moodle "grep -E '^\s*\\\$build\s*=' '$MOODLE_ROOT_IN_CONTAINER/version.php' | tr -d ' ;' " 2>/dev/null || echo "unknown"
}

run_phpunit_init() {
    local output
    if ! output=$(exec_as_root "php '$MOODLE_ROOT_IN_CONTAINER/admin/tool/phpunit/cli/init.php' 2>&1"); then
        echo "$output"
        return 1
    fi
    echo "$output" | grep -Ev '^A new stable major version of Composer is available|^You are already using composer version 1|^You are using Composer 1 which is deprecated' || true
}

log "Verificando container '$CONTAINER_NAME'..."
if container_is_running; then
    log "Container já está rodando."
else
    warn "Container não está rodando. Iniciando via docker compose..."
    (cd "$DOCKER_COMPOSE_DIR" && docker compose up -d --remove-orphans "$CONTAINER_NAME")
    log "Aguardando container inicializar..."
    for i in $(seq 1 12); do
        sleep 5
        if container_is_running; then
            log "Container pronto após $((i * 5))s."
            break
        fi
        echo -n "."
    done
    if ! container_is_running; then
        err "Falha ao iniciar o container '$CONTAINER_NAME'. Verifique: docker compose logs $CONTAINER_NAME"
    fi
fi

log "Verificando Composer..."
COMPOSER_OK=$(exec_as_root "composer --version 2>/dev/null | grep -q 'Composer version 1\.' && echo yes || echo no" 2>/dev/null || echo "no")
if [ "$COMPOSER_OK" != "yes" ]; then
    log "Composer 1.x não encontrado. Instalando..."
    exec_as_root "
        curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php &&
        php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --1 &&
        rm /tmp/composer-setup.php
    "
    log "Composer 1.x instalado."
fi

log "Verificando extensões PHP necessárias para o PHPUnit..."
XMLWRITER_OK=$(exec_as_root "php -m 2>/dev/null | grep -q xmlwriter && echo yes || echo no")
if [ "$XMLWRITER_OK" != "yes" ]; then
    log "Instalando extensão php7-xmlwriter (necessária para phpunit/php-code-coverage)..."
    exec_as_root "apk add --no-cache php7-xmlwriter"
    log "php7-xmlwriter instalado."
fi

log "Verificando dependências Composer do Moodle (vendor/)..."
VENDOR_EXISTS=$(exec_as_moodle "test -f '$MOODLE_ROOT_IN_CONTAINER/vendor/bin/phpunit' && echo yes || echo no" 2>/dev/null || echo "no")
if [ "$VENDOR_EXISTS" != "yes" ]; then
    log "Dependências ausentes. Preparando diretório e executando 'composer install'..."
    exec_as_root "
        mkdir -p '$MOODLE_ROOT_IN_CONTAINER/vendor' &&
        chown -R moodle:moodle '$MOODLE_ROOT_IN_CONTAINER/vendor'
    "
    exec_as_moodle "
        git config --global --add safe.directory '$MOODLE_ROOT_IN_CONTAINER' 2>/dev/null || true
    "
    exec_as_moodle "
        cd '$MOODLE_ROOT_IN_CONTAINER' &&
        composer install --no-interaction --prefer-dist
    "
    log "Dependências instaladas."
fi

log "Verificando configuração PHPUnit no config.php..."
PHPUNIT_CONFIGURED=$(exec_as_root "
    grep -q \"phpunit_prefix\" '$MOODLE_ROOT_IN_CONTAINER/config.php' &&
    grep -v '^[[:space:]]*//' '$MOODLE_ROOT_IN_CONTAINER/config.php' | grep -q 'phpunit_prefix' &&
    echo yes || echo no
" 2>/dev/null || echo "no")

if [ "$PHPUNIT_CONFIGURED" != "yes" ]; then
    warn "PHPUnit não configurado no config.php. Adicionando configurações..."
    exec_as_root "mkdir -p '$PHPUNIT_DATAROOT' && chown -R moodle:moodle '$PHPUNIT_DATAROOT'"
    exec_as_root "
        sed -i \"/require_once.*lib\/setup\.php/i\\
\\\$CFG->phpunit_prefix = '$PHPUNIT_PREFIX';\\
\\\$CFG->phpunit_dataroot = '$PHPUNIT_DATAROOT';\\
\\\$CFG->phpunit_directorypermissions = 02777;
\" '$MOODLE_ROOT_IN_CONTAINER/config.php'
    "
    log "Configurações PHPUnit adicionadas ao config.php."
fi

PHPUNIT_XML="$MOODLE_ROOT_IN_CONTAINER/phpunit.xml"
PHPUNIT_VERSION_MARKER="$PHPUNIT_DATAROOT/moodle_build_marker"

exec_as_root "mkdir -p '$PHPUNIT_DATAROOT' && chown -R moodle:moodle '$PHPUNIT_DATAROOT'"

if [ -n "$RESET_FLAG" ]; then
    log "Reinicializando tabelas PHPUnit (--reset)..."
    exec_as_root "php '$MOODLE_ROOT_IN_CONTAINER/admin/tool/phpunit/cli/util.php' --drop"
    run_phpunit_init
    CURRENT_BUILD=$(get_moodle_build)
    exec_as_moodle "echo '$CURRENT_BUILD' > '$PHPUNIT_VERSION_MARKER'"
    log "PHPUnit reinicializado."

elif ! exec_as_moodle "test -f '$PHPUNIT_XML'" 2>/dev/null; then
    log "Inicializando PHPUnit pela primeira vez (pode demorar alguns minutos)..."
    MOODLE_HOST_DIR="$DOCKER_COMPOSE_DIR/$MOODLE_LOCAL_SITE"
    log "Ajustando permissões no host para permitir escrita pelo container..."
    chmod a+w "$MOODLE_HOST_DIR"
    COMPOSER_PHAR="$MOODLE_ROOT_IN_CONTAINER/composer.phar"
    COMPOSER_PHAR_HOST="$MOODLE_HOST_DIR/composer.phar"
    if [ ! -f "$COMPOSER_PHAR_HOST" ]; then
        log "Criando composer.phar como wrapper do composer global..."
        exec_as_root "ln -sf /usr/local/bin/composer '$COMPOSER_PHAR'"
    fi
    run_phpunit_init
    CURRENT_BUILD=$(get_moodle_build)
    exec_as_moodle "echo '$CURRENT_BUILD' > '$PHPUNIT_VERSION_MARKER'"
    log "PHPUnit inicializado com sucesso."
else
    CURRENT_BUILD=$(get_moodle_build)
    STORED_BUILD=$(exec_as_moodle "cat '$PHPUNIT_VERSION_MARKER' 2>/dev/null || echo ''" 2>/dev/null || echo "")
    if [ "$CURRENT_BUILD" != "$STORED_BUILD" ] || [ -z "$STORED_BUILD" ]; then
        log "Versão do Moodle alterada. Atualizando ambiente PHPUnit..."
        run_phpunit_init
        exec_as_moodle "echo '$CURRENT_BUILD' > '$PHPUNIT_VERSION_MARKER'"
        log "PHPUnit atualizado com sucesso."
    else
        log "PHPUnit já inicializado e compatível."
    fi
fi

echo ""
log "============================================================"
log " Executando testes: $PLUGIN_COMPONENT"
log "============================================================"

PLUGIN_TEST_DIR="$MOODLE_ROOT_IN_CONTAINER/$PLUGIN_PATH/tests"

if [ -n "$TEST_FILE" ]; then
    if [[ "$TEST_FILE" == /* ]]; then
        TEST_PATH="$TEST_FILE"
    else
        TEST_PATH="$MOODLE_ROOT_IN_CONTAINER/$PLUGIN_PATH/$TEST_FILE"
    fi
    log "Arquivo: $TEST_PATH"
    echo ""
    exec_as_root "
        cd '$MOODLE_ROOT_IN_CONTAINER' &&
        php vendor/bin/phpunit --colors=always '$TEST_PATH'
    "
else
    log "Diretório: $PLUGIN_TEST_DIR"
    echo ""
    exec_as_root "
        set -e
        cd '$MOODLE_ROOT_IN_CONTAINER'
        TEST_FILES=\$(find '$PLUGIN_TEST_DIR' -type f -name '*_test.php' | sort)

        if [ -z \"\$TEST_FILES\" ]; then
            echo '[ERROR] Nenhum arquivo *_test.php encontrado em $PLUGIN_TEST_DIR' >&2
            exit 1
        fi

        for file in \$TEST_FILES; do
            php vendor/bin/phpunit --colors=always \"\$file\"
        done
    "
fi

echo ""
log "============================================================"
log " Testes concluídos."
log "============================================================"
```

### Passo 3 — `run_behat.sh`

Salve em `<plugin>/run_behat.sh` e `chmod +x run_behat.sh`:

```bash
#!/bin/bash
# =============================================================================
# run_behat.sh - Executa testes Behat do plugin via Docker
#
# Uso:
#   ./run_behat.sh                                  # Roda todos os testes da tag padrão
#   ./run_behat.sh tests/behat/x.feature            # Roda um feature file específico
#   ./run_behat.sh --tags=@xx                       # Filtra por tag
#   ./run_behat.sh --name="meu cenário"             # Filtra por nome do cenário
#   ./run_behat.sh --init                           # Força reinicialização do ambiente Behat
#
# Configuração (via .env):
#   CORE_NAME, DOCKER_VERSION, URL_NAME, PLUGIN_COMPONENT, PLUGIN_PATH, PLUGIN_TAG (opcional)
# =============================================================================

set -e

log()  { echo -e "\033[0;32m[INFO]\033[0m  $*"; }
warn() { echo -e "\033[0;33m[WARN]\033[0m  $*"; }
err()  { echo -e "\033[0;31m[ERROR]\033[0m $*" >&2; exit 1; }

if [ -f "../../../../.env" ]; then
  set -a
  source ../../../../.env
  set +a
else
  err "Arquivo ../../../../.env (stack docker) não encontrado."
fi

if [ -f ".env" ]; then
  set -a
  source .env
  set +a
else
  err "Arquivo .env não encontrado."
fi

[ -n "$CORE_NAME" ]        || err "Variável CORE_NAME ausente no .env."
[ -n "$DOCKER_VERSION" ]   || err "Variável DOCKER_VERSION ausente no .env."
[ -n "$URL_NAME" ]         || err "Variável URL_NAME ausente no .env."
[ -n "$PLUGIN_COMPONENT" ] || err "Variável PLUGIN_COMPONENT ausente no .env."
[ -n "$PLUGIN_PATH" ]      || err "Variável PLUGIN_PATH ausente no .env."
[ -n "$BEHAT_PREFIX" ]     || err "Variável BEHAT_PREFIX ausente (esperada no .env do stack docker)."

SISTEM_NAME="local-$CORE_NAME"
CONTAINER_NAME="moodle-$SISTEM_NAME"
SELENIUM_CONTAINER="selenium-chrome-$CORE_NAME"
SELENIUM_IMAGE="selenium/standalone-chrome:3.141.59-selenium"
DOCKER_COMPOSE_DIR="/home/$USER/workspace/docker/$DOCKER_VERSION"
MOODLE_LOCAL_SITE="www/$SISTEM_NAME"
MOODLE_ROOT_IN_CONTAINER="/home/moodle/$MOODLE_LOCAL_SITE"
DOCKER_NETWORK="moodle-network-$DOCKER_VERSION"
BEHAT_DATAROOT="/home/moodle/moodledata/${BEHAT_PREFIX}$SISTEM_NAME"
BEHAT_WWWROOT="http://$URL_NAME"
BEHAT_ENABLE_FILE="/tmp/.${BEHAT_PREFIX}${SISTEM_NAME}_enabled"
PLUGIN_TAG="${PLUGIN_TAG:-@$PLUGIN_COMPONENT}"
MOODLE_ENABLE_BEHAT=1

INIT_FLAG=""
FEATURE_FILE=""
TAGS_ARG=""
BEHAT_EXTRA_ARGS=()
for arg in "$@"; do
    case "$arg" in
        --init)       INIT_FLAG="yes" ;;
        --tags=*)     TAGS_ARG="$arg" ;;
        -*)           BEHAT_EXTRA_ARGS+=("$arg") ;;
        *)            FEATURE_FILE="$arg" ;;
    esac
done

build_escaped_args() {
    local out=""
    local arg
    for arg in "$@"; do
        out="$out $(printf "%q" "$arg")"
    done
    echo "$out"
}

container_is_running() {
    docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q "true"
}

exec_as_moodle() { docker exec -u moodle "$CONTAINER_NAME" bash -c "$1"; }
exec_php_as_moodle_for_init() { docker exec -u moodle "$CONTAINER_NAME" bash -c "$1"; }

enable_behat_environment() {
    log "Ativando configuração Behat para esta execução..."
    docker exec -u 0 "$CONTAINER_NAME" bash -c "mkdir -p /home/moodle/moodledata && chown moodle:moodle /home/moodle/moodledata && chmod 755 /home/moodle/moodledata"
    exec_as_moodle "mkdir -p '$BEHAT_DATAROOT' && touch '$BEHAT_ENABLE_FILE' && rm -f '$BEHAT_DATAROOT/.behat_enabled'"
}

disable_behat_environment() {
    if ! container_is_running "$CONTAINER_NAME"; then return; fi
    log "Desabilitando modo Behat para restaurar o ambiente local..."
    exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util.php' --disable 2>&1 || true"
    exec_as_moodle "rm -f '$BEHAT_ENABLE_FILE' '$BEHAT_DATAROOT/.behat_enabled'"
}

ensure_behat_test_mode_enabled() {
    log "Garantindo que o modo de testes do Behat esteja habilitado..."
    exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util.php' --enable 2>&1"
}

cleanup() { disable_behat_environment; }
trap cleanup EXIT

ensure_legacy_composer_for_behat_init() {
    log "Preparando composer legado para inicialização do Behat..."
    TMP_COMPOSER_WRAPPER=$(mktemp)
    cat > "$TMP_COMPOSER_WRAPPER" <<'PHPWRAPPER'
<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$args = $_SERVER['argv'];
array_shift($args);
if (!empty($args) && $args[0] === 'self-update') {
    fwrite(STDOUT, "Skipping composer self-update for legacy PHP environment\n");
    exit(0);
}
$real = __DIR__ . '/composer-real.phar';
$cmd = 'USE_ZEND_ALLOC=0 php -d opcache.enable_cli=0 ' . escapeshellarg($real);
foreach ($args as $arg) { $cmd .= ' ' . escapeshellarg($arg); }
passthru($cmd, $exitcode);
exit($exitcode);
PHPWRAPPER

    docker exec -u 0 "$CONTAINER_NAME" bash -c "set -e
        curl -sS -L -o /tmp/composer22.phar https://github.com/composer/composer/releases/download/2.2.21/composer.phar
        cp /tmp/composer22.phar '$MOODLE_ROOT_IN_CONTAINER/composer-real.phar'
    "
    docker cp "$TMP_COMPOSER_WRAPPER" "$CONTAINER_NAME:$MOODLE_ROOT_IN_CONTAINER/composer.phar"
    rm -f "$TMP_COMPOSER_WRAPPER"

    docker exec -u 0 "$CONTAINER_NAME" bash -c "set -e
        chown moodle:moodle '$MOODLE_ROOT_IN_CONTAINER/composer-real.phar'
        chmod 555 '$MOODLE_ROOT_IN_CONTAINER/composer-real.phar'
        chown moodle:moodle '$MOODLE_ROOT_IN_CONTAINER/composer.phar'
        chmod 555 '$MOODLE_ROOT_IN_CONTAINER/composer.phar'
    "
}

# --- 1. Container Moodle ---
log "Verificando container '$CONTAINER_NAME'..."
if container_is_running "$CONTAINER_NAME"; then
    log "Container Moodle já está rodando."
else
    warn "Container não está rodando. Iniciando via docker compose..."
    (cd "$DOCKER_COMPOSE_DIR" && docker compose up -d --remove-orphans "$CONTAINER_NAME")
    for i in $(seq 1 12); do
        sleep 5
        if container_is_running "$CONTAINER_NAME"; then
            log "Container Moodle pronto após $((i * 5))s."; break
        fi
        echo -n "."
    done
    container_is_running "$CONTAINER_NAME" || err "Falha ao iniciar '$CONTAINER_NAME'."
fi

# --- 2. Container Selenium ---
log "Verificando container Selenium '$SELENIUM_CONTAINER'..."
if ! docker network inspect "$DOCKER_NETWORK" >/dev/null 2>&1; then
    warn "Rede Docker '$DOCKER_NETWORK' não encontrada. Criando..."
    docker network create "$DOCKER_NETWORK" >/dev/null
fi

if docker inspect "$SELENIUM_CONTAINER" &>/dev/null; then
    EXISTING_SELENIUM_IMAGE=$(docker inspect -f '{{.Config.Image}}' "$SELENIUM_CONTAINER" 2>/dev/null || true)
    if [ -n "$EXISTING_SELENIUM_IMAGE" ] && [ "$EXISTING_SELENIUM_IMAGE" != "$SELENIUM_IMAGE" ]; then
        warn "Container Selenium usa imagem '$EXISTING_SELENIUM_IMAGE' (esperado '$SELENIUM_IMAGE'). Recriando..."
        docker rm -f "$SELENIUM_CONTAINER" >/dev/null 2>&1 || true
    fi
fi

if container_is_running "$SELENIUM_CONTAINER"; then
    log "Container Selenium já está rodando."
    docker network connect "$DOCKER_NETWORK" "$SELENIUM_CONTAINER" 2>/dev/null || true
else
    if docker inspect "$SELENIUM_CONTAINER" &>/dev/null; then
        log "Reiniciando container Selenium existente..."
        START_OUTPUT=""
        if ! START_OUTPUT=$(docker start "$SELENIUM_CONTAINER" 2>&1); then
            if echo "$START_OUTPUT" | grep -qi "network .* not found"; then
                warn "Container Selenium preso a rede removida. Recriando container..."
                docker rm -f "$SELENIUM_CONTAINER" >/dev/null 2>&1 || true
                docker run -d --name "$SELENIUM_CONTAINER" --network "$DOCKER_NETWORK" --shm-size=2g -p 4444:4444 "$SELENIUM_IMAGE"
            else
                err "Falha ao iniciar '$SELENIUM_CONTAINER': $START_OUTPUT"
            fi
        fi
    else
        log "Iniciando novo container Selenium (imagem: $SELENIUM_IMAGE)..."
        docker run -d --name "$SELENIUM_CONTAINER" --network "$DOCKER_NETWORK" --shm-size=2g -p 4444:4444 "$SELENIUM_IMAGE"
    fi
    log "Aguardando Selenium inicializar..."
    for i in $(seq 1 12); do
        sleep 5
        if docker exec "$SELENIUM_CONTAINER" curl -sf http://localhost:4444/wd/hub/status &>/dev/null; then
            log "Selenium pronto após $((i * 5))s."; break
        fi
        echo -n "."
    done
fi

# --- /etc/hosts (resolução de URL_NAME) ---
log "Configurando /etc/hosts do Selenium para resolver '$URL_NAME'..."
MOODLE_IP=$(docker inspect -f "{{(index .NetworkSettings.Networks \"$DOCKER_NETWORK\").IPAddress}}" "$CONTAINER_NAME" 2>/dev/null)
[ -n "$MOODLE_IP" ] && [ "$MOODLE_IP" != "<no value>" ] || err "Não foi possível obter IP do container '$CONTAINER_NAME' na rede '$DOCKER_NETWORK'."
docker exec -u 0 "$SELENIUM_CONTAINER" bash -c "TMP=/tmp/hosts.\$\$; grep -v '[[:space:]]$URL_NAME$' /etc/hosts > \"\$TMP\" || true; cat \"\$TMP\" > /etc/hosts; rm -f \"\$TMP\"; echo '$MOODLE_IP $URL_NAME' >> /etc/hosts"
log "Selenium resolve '$URL_NAME' -> $MOODLE_IP."

log "Configurando /etc/hosts do Moodle para resolver '$URL_NAME' -> 127.0.0.1..."
docker exec -u 0 "$CONTAINER_NAME" bash -c "TMP=/tmp/hosts.\$\$; grep -v '[[:space:]]$URL_NAME$' /etc/hosts > \"\$TMP\" || true; cat \"\$TMP\" > /etc/hosts; rm -f \"\$TMP\"; echo '127.0.0.1 $URL_NAME' >> /etc/hosts"
log "Moodle resolve '$URL_NAME' -> 127.0.0.1."

# --- 3. Behat enable + config.php ---
enable_behat_environment

log "Verificando configuração Behat no config.php..."
BEHAT_CONFIGURED=$(exec_as_moodle "grep -v '^[[:space:]]*//' '$MOODLE_ROOT_IN_CONTAINER/config.php' | grep -q 'behat_prefix' && echo yes || echo no" 2>/dev/null || echo "no")
if [ "$BEHAT_CONFIGURED" != "yes" ]; then
    warn "Behat não configurado no config.php. Adicionando configurações..."
    docker exec -u 0 "$CONTAINER_NAME" bash -c "mkdir -p /home/moodle/moodledata && chown moodle:moodle /home/moodle/moodledata && chmod 755 /home/moodle/moodledata"
    exec_as_moodle "mkdir -p '$BEHAT_DATAROOT' && chown -R moodle:moodle '$BEHAT_DATAROOT'"
    exec_as_moodle "sed -i \"/require_once.*lib\/setup\.php/i\\
\\\$CFG->behat_wwwroot  = '$BEHAT_WWWROOT';\\
\\\$CFG->behat_prefix   = '$BEHAT_PREFIX';\\
\\\$CFG->behat_dataroot = '$BEHAT_DATAROOT';\\
\\\$CFG->behat_config   = array(\\
    'default' => array(\\
        'extensions' => array(\\
            'Behat\\\\\\\\MinkExtension\\\\\\\\Extension' => array(\\
                'selenium2' => array(\\
                    'browser'      => 'chrome',\\
                    'capabilities' => array('chrome' => array('switches' => array('--no-sandbox', '--disable-dev-shm-usage'))),\\
                    'wd_host'      => 'http://$SELENIUM_CONTAINER:4444/wd/hub',\\
                ),\\
            ),\\
        ),\\
    ),\\
);
\" '$MOODLE_ROOT_IN_CONTAINER/config.php'"
    log "Configurações Behat adicionadas ao config.php."
fi

# Auto-correção 1: chromeOptions/extra_capabilities desatualizado
BEHAT_CONFIG_STALE=$(exec_as_moodle "grep -q 'chromeOptions\|extra_capabilities' '$MOODLE_ROOT_IN_CONTAINER/config.php' && echo yes || echo no" 2>/dev/null || echo "no")
if [ "$BEHAT_CONFIG_STALE" = "yes" ]; then
    warn "Configuração Behat desatualizada. Corrigindo config.php..."
    exec_as_moodle "php -r \"
        \\\$f = file_get_contents('$MOODLE_ROOT_IN_CONTAINER/config.php');
        \\\$old = array(
            \\\"'capabilities' => array('chromeOptions' => array('args' => array('--headless', '--no-sandbox', '--disable-dev-shm-usage')))\\\",
            \\\"'capabilities' => array('extra_capabilities' => array('chromeOptions' => array('args' => array('--headless', '--no-sandbox', '--disable-dev-shm-usage'))))\\\",
        );
        \\\$new = \\\"'capabilities' => array('chrome' => array('switches' => array('--no-sandbox', '--disable-dev-shm-usage')))\\\";
        \\\$f = str_replace(\\\$old, \\\$new, \\\$f);
        file_put_contents('$MOODLE_ROOT_IN_CONTAINER/config.php', \\\$f);
    \""
    log "config.php corrigido. Forçando reinicialização do Behat..."
    INIT_FLAG="yes"
fi

# Auto-correção 2: behat_dataroot desatualizado
BEHAT_DATAROOT_ACTUAL=$(exec_as_moodle "grep -v '^[[:space:]]*//' '$MOODLE_ROOT_IN_CONTAINER/config.php' | grep 'behat_dataroot' | grep -o \"'[^']*'\" | tr -d \"'\"" 2>/dev/null || true)
if [ -n "$BEHAT_DATAROOT_ACTUAL" ] && [ "$BEHAT_DATAROOT_ACTUAL" != "$BEHAT_DATAROOT" ]; then
    warn "behat_dataroot no config.php ('$BEHAT_DATAROOT_ACTUAL') diferente do esperado ('$BEHAT_DATAROOT'). Corrigindo..."
    exec_as_moodle "sed -i \"s|behat_dataroot = '$BEHAT_DATAROOT_ACTUAL'|behat_dataroot = '$BEHAT_DATAROOT'|g\" '$MOODLE_ROOT_IN_CONTAINER/config.php'"
    log "behat_dataroot corrigido. Forçando reinicialização do Behat..."
    INIT_FLAG="yes"
fi

# Auto-correção 3: behat_wwwroot desatualizado (apontando para container)
BEHAT_WWWROOT_STALE=$(exec_as_moodle "grep -q \"behat_wwwroot.*moodle-$SISTEM_NAME\" '$MOODLE_ROOT_IN_CONTAINER/config.php' && echo yes || echo no" 2>/dev/null || echo "no")
if [ "$BEHAT_WWWROOT_STALE" = "yes" ]; then
    warn "behat_wwwroot aponta para container em vez de '$BEHAT_WWWROOT'. Corrigindo..."
    exec_as_moodle "sed -i 's|http://$CONTAINER_NAME|$BEHAT_WWWROOT|g' '$MOODLE_ROOT_IN_CONTAINER/config.php'"
    log "behat_wwwroot corrigido. Forçando reinicialização do Behat..."
    INIT_FLAG="yes"
fi

# --- 4. Init Behat (se necessário) ---
BEHAT_YML="$BEHAT_DATAROOT/behat/behat.yml"

# Probe: se behat.yml existe mas as tabelas foram dropadas, força --init.
if [ -z "$INIT_FLAG" ] && exec_as_moodle "test -f '$BEHAT_YML'" 2>/dev/null; then
    PROBE_OUTPUT=$(exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util.php' --enable 2>&1" || true)
    if echo "$PROBE_OUTPUT" | grep -qi "Install Behat before enabling"; then
        warn "Tabelas Behat ausentes ('Install Behat before enabling'). Forçando reinicialização..."
        INIT_FLAG="yes"
    fi
fi

if [ -n "$INIT_FLAG" ]; then
    log "Reinicializando ambiente Behat (--init)..."
    ensure_legacy_composer_for_behat_init
    exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util.php' --enable 2>&1 || true"
    exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util_single_run.php' --drop 2>&1 || true"
    INIT_OUTPUT=""
    if ! INIT_OUTPUT=$(exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/init.php' 2>&1"); then
        if echo "$INIT_OUTPUT" | grep -qi "upgraderunning"; then
            warn "Lock 'upgraderunning' detectado. Limpando e tentando novamente..."
            exec_php_as_moodle_for_init "php -r \"
                require('$MOODLE_ROOT_IN_CONTAINER/config.php');
                global \\$DB;
                \\$DB->delete_records('config', array('name' => 'upgraderunning'));
            \" 2>&1"
            INIT_OUTPUT=$(exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/init.php' 2>&1") || err "Falha ao inicializar Behat: $INIT_OUTPUT"
        elif echo "$INIT_OUTPUT" | grep -qi "This is not a behat test site"; then
            warn "Site ainda não está em modo Behat. Forçando habilitação..."
            exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/util.php' --enable 2>&1 || true"
            INIT_OUTPUT=$(exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/init.php' 2>&1") || err "Falha ao inicializar Behat: $INIT_OUTPUT"
        else
            err "Falha ao inicializar Behat: $INIT_OUTPUT"
        fi
    fi
    log "Behat reinicializado."

elif ! exec_as_moodle "test -f '$BEHAT_YML'" 2>/dev/null; then
    log "Inicializando ambiente Behat pela primeira vez (pode demorar alguns minutos)..."
    MOODLE_HOST_DIR="$DOCKER_COMPOSE_DIR/$MOODLE_LOCAL_SITE"
    chmod a+w "$MOODLE_HOST_DIR"
    ensure_legacy_composer_for_behat_init
    exec_php_as_moodle_for_init "MOODLE_SKIP_COMPOSER_SELF_UPDATE=1 USE_ZEND_ALLOC=0 php -d memory_limit=512M '$MOODLE_ROOT_IN_CONTAINER/admin/tool/behat/cli/init.php' 2>&1"
    log "Behat inicializado com sucesso."
else
    log "Ambiente Behat já inicializado."
fi

ensure_behat_test_mode_enabled

# --- 5. Diagnóstico e execução ---
echo ""
log "============================================================"
log " Executando testes Behat: $PLUGIN_COMPONENT"
log "============================================================"

log "Diagnóstico: verificando front page behat em http://$URL_NAME/ ..."
DIAG_TITLE=$(docker exec "$SELENIUM_CONTAINER" bash -c "curl -sL --max-time 10 'http://$URL_NAME/' 2>&1 | grep -o '<title>[^<]*</title>'" 2>/dev/null || echo "(curl falhou)")
log "  Título da página: ${DIAG_TITLE:-(sem título / página em branco)}"
DIAG_STATUS=$(docker exec "$SELENIUM_CONTAINER" bash -c "curl -so /dev/null -w '%{http_code}' --max-time 10 'http://$URL_NAME/'" 2>/dev/null || echo "???")
log "  HTTP status: $DIAG_STATUS"

BEHAT_CMD="cd '$MOODLE_ROOT_IN_CONTAINER' && vendor/bin/behat --config='$BEHAT_YML' --ansi"
EXTRA_ARGS_ESCAPED="$(build_escaped_args "${BEHAT_EXTRA_ARGS[@]}")"

if [ -n "$FEATURE_FILE" ]; then
    if [[ "$FEATURE_FILE" == /* ]]; then
        FEATURE_PATH="$FEATURE_FILE"
    else
        FEATURE_PATH="$MOODLE_ROOT_IN_CONTAINER/$PLUGIN_PATH/$FEATURE_FILE"
    fi
    log "Feature: $FEATURE_PATH"
    echo ""
    exec_as_moodle "$BEHAT_CMD $(printf "%q" "$FEATURE_PATH")$EXTRA_ARGS_ESCAPED"
elif [ -n "$TAGS_ARG" ]; then
    log "Tags: $TAGS_ARG"
    echo ""
    exec_as_moodle "$BEHAT_CMD $(printf "%q" "$TAGS_ARG")$EXTRA_ARGS_ESCAPED"
else
    log "Tag padrão: $PLUGIN_TAG"
    echo ""
    exec_as_moodle "$BEHAT_CMD --tags='$PLUGIN_TAG'$EXTRA_ARGS_ESCAPED"
fi

echo ""
log "============================================================"
log " Testes Behat concluídos."
log "============================================================"
```

### Passo 4 — `stop_behat.sh`

Salve em `<plugin>/stop_behat.sh` e `chmod +x stop_behat.sh`:

```bash
#!/bin/bash
# =============================================================================
# stop_behat.sh - Para containers usados pelo run_behat.sh
#
# Uso:
#   ./stop_behat.sh         # Para somente containers do Behat (Moodle + Selenium)
#   ./stop_behat.sh --down  # Também executa docker compose down no ambiente
# =============================================================================

set -e

log()  { echo -e "\033[0;32m[INFO]\033[0m  $*"; }
warn() { echo -e "\033[0;33m[WARN]\033[0m  $*"; }
err()  { echo -e "\033[0;31m[ERROR]\033[0m $*" >&2; exit 1; }

if [ -f ".env" ]; then
  set -a
  source .env
  set +a
else
  err "Arquivo .env não encontrado."
fi

SISTEM_NAME="local-$CORE_NAME"
CONTAINER_NAME="moodle-$SISTEM_NAME"
SELENIUM_CONTAINER="selenium-chrome-$CORE_NAME"
DOCKER_COMPOSE_DIR="/home/$USER/workspace/docker/$DOCKER_VERSION"

DOWN_FLAG=""
for arg in "$@"; do
    case "$arg" in
        --down) DOWN_FLAG="yes" ;;
        *) ;;
    esac
done

container_exists() { docker inspect "$1" >/dev/null 2>&1; }
container_is_running() { docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null | grep -q "true"; }

stop_container_if_running() {
    local name="$1"
    if container_exists "$name"; then
        if container_is_running "$name"; then
            log "Parando container '$name'..."
            docker stop "$name" >/dev/null
            log "Container '$name' parado."
        else
            warn "Container '$name' já está parado."
        fi
    else
        warn "Container '$name' não existe."
    fi
}

log "Parando containers do Behat..."
stop_container_if_running "$CONTAINER_NAME"
stop_container_if_running "$SELENIUM_CONTAINER"

if [ -n "$DOWN_FLAG" ]; then
    log "Executando docker compose down em '$DOCKER_COMPOSE_DIR'..."
    (cd "$DOCKER_COMPOSE_DIR" && docker compose down)
    log "docker compose down concluído."
fi

log "Finalizado."
```

### Passo 5 (opcional) — `TESTS.md`

Documente os testes específicos do seu plugin em um `TESTS.md` no mesmo diretório, descrevendo: quais features Behat existem, quais classes PHPUnit testam o quê, e exemplos de uso. Veja o `TESTS.md` deste plugin como referência.

---

## Como usar

```bash
# PHPUnit
./run_tests.sh                           # todos os arquivos *_test.php
./run_tests.sh tests/foo_test.php        # um arquivo
./run_tests.sh --reset                   # zera e reinicializa banco PHPUnit

# Behat
./run_behat.sh                           # todos os cenários com PLUGIN_TAG
./run_behat.sh tests/behat/x.feature     # um feature file
./run_behat.sh --tags=@xx                # outra tag
./run_behat.sh --name="meu cenário"      # filtrar por nome
./run_behat.sh --init                    # forçar reinicialização

# Stop
./stop_behat.sh                          # para containers Moodle + Selenium
./stop_behat.sh --down                   # também faz docker compose down
```

---

## Como funciona (arquitetura)

### Container Moodle

| Caminho dentro do container | Conteúdo |
|---|---|
| `/home/moodle/www/local-$CORE_NAME/` | Moodle root (volume mount do host) |
| `/home/moodle/moodledata/phpu_local-$CORE_NAME/` | PHPUnit dataroot |
| `/home/moodle/moodledata/${BEHAT_PREFIX}local-$CORE_NAME/` | Behat dataroot |
| `/home/moodle/www/local-$CORE_NAME/config.php` | Recebe `phpunit_*` e `behat_*` injetados via `sed` |

Os scripts usam dois usuários no container:

- `moodle` (não-root): `composer install`, `init.php`, criação de `vendor/`, execução do PHPUnit/Behat. Evita problemas de permissão no host por causa de user namespace mapping do Docker.
- `root` (`-u 0`): instalar pacotes Alpine (`apk add`), ajustar `/etc/hosts`, criar diretórios, ajustar ownership.

### Container Selenium

- Imagem **fixa** `selenium/standalone-chrome:3.141.59-selenium` (Chrome 75, ChromeDriver 75). **Não atualizar** — Chrome ≥ 76 quebra a compatibilidade com Mink/Behat 2.x (chromedriver passou a responder só em W3C; Mink antigo só lê OSS WebDriver).
- Compartilhado entre plugins do mesmo `CORE_NAME` (mesmo container Moodle = mesmo Selenium).
- Conectado à mesma rede Docker que o Moodle (`moodle-network-$DOCKER_VERSION`).
- `--shm-size=2g` evita crashes de memória compartilhada do Chrome.
- O script detecta se o container existente tem imagem errada e recria.

### `/etc/hosts` injetados (crítico)

| Container | Mapeamento | Por quê |
|---|---|---|
| **Moodle** | `127.0.0.1 $URL_NAME` | `behat init` e checks de `behat_wwwroot` saem por DNS externo e podem acertar um servidor remoto que responda pelo mesmo domínio (301 → HTTPS). |
| **Selenium** | `$MOODLE_IP $URL_NAME` | Docker DNS resolve nomes de container mas não o domínio externo configurado em `behat_wwwroot`. O Chrome precisa enxergar a URL do Moodle. |

### Wrapper do Composer 2.2 para Behat init

O `admin/tool/behat/cli/init.php` do Moodle baixa um `composer.phar` no dirroot e chama `self-update` em runtime — quebra em PHP 5.6 porque Composer recente exige PHP ≥ 7.x. Solução:

1. Baixa Composer 2.2.21 como `composer-real.phar` (última versão que roda em PHP 5.6).
2. Cria `composer.phar` como wrapper PHP que **ignora `self-update`** e delega o resto para `composer-real.phar` com `USE_ZEND_ALLOC=0`.

### Limpeza automática (Behat)

`trap cleanup EXIT` no `run_behat.sh` chama `admin/tool/behat/cli/util.php --disable` no fim, devolvendo o site ao modo normal. Você pode usar o Moodle normalmente entre runs.

### Auto-correções no `config.php`

`run_behat.sh` detecta e corrige automaticamente três formas de configuração obsoleta no `config.php`:

1. **`chromeOptions` / `extra_capabilities`** (formato antigo) → substitui por `capabilities.chrome.switches` (formato esperado por esta versão do Mink).
2. **`behat_dataroot` com prefixo errado** (ex.: `behat_` em vez de `bht_`) → ajusta para o valor calculado a partir do `.env`.
3. **`behat_wwwroot` apontando para nome do container** (ex.: `http://moodle-local-...`) → ajusta para `http://$URL_NAME`.

Se qualquer correção for aplicada, força `--init` na sequência.

### Marker de versão do Moodle (PHPUnit)

`run_tests.sh` grava `$build` da Moodle em `$PHPUNIT_DATAROOT/moodle_build_marker`. Se a versão do Moodle mudar entre runs (upgrade), o `init.php` é refeito automaticamente.

---

## Troubleshooting

| Sintoma | Causa provável | Solução |
|---|---|---|
| `Selenium not reachable` / timeout no Chrome | Container Selenium com imagem errada (Chrome ≥ 76) | Confirme imagem `selenium/standalone-chrome:3.141.59-selenium`. O script recria automaticamente se detectar mismatch — se não recriou, force: `docker rm -f selenium-chrome-$CORE_NAME && ./run_behat.sh`. |
| `This is not a behat test site` | Site ainda não foi habilitado para modo Behat | Use `./run_behat.sh --init` — o script força `util.php --enable` antes do `init.php`. |
| `Install Behat before enabling it, use: php init.php` | Tabelas Behat foram dropadas (ex.: `--drop` em run anterior) | O script detecta automaticamente via probe `util.php --enable` antes da execução e força `--init`. Se aparecer mesmo assim, rode `./run_behat.sh --init` manualmente. |
| `upgraderunning` durante init | Lock órfão na tabela `config` | O script detecta e remove o lock automaticamente quando usa `--init`. |
| `composer install` falha por ownership | User namespace mapping do Docker | O script roda `chmod a+w` no `MOODLE_HOST_DIR` antes do init. Se o problema persistir, confira ownership de `vendor/` no host. |
| 301 → HTTPS na frontpage durante init Behat | `/etc/hosts` no Moodle container não tem `127.0.0.1 $URL_NAME` | O script faz esse mapeamento. Confira com `docker exec -u 0 moodle-local-$CORE_NAME cat /etc/hosts`. |
| Erro de memória no PHPUnit/Behat | `PHP_MEMORY_LIMIT` baixo no `.env` do stack | Aumente para `2048M` ou mais. Behat init usa `-d memory_limit=512M` fixo. |
| `[ERROR] Nenhum arquivo *_test.php encontrado` | Plugin ainda não tem testes PHPUnit | Esperado — sinal de que ainda não há unitários. Crie `tests/foo_test.php` quando for hora. |
| `Variável CORE_NAME ausente no .env` | `.env` faltando ou variável não exportada | Confira o `.env` na raiz do plugin. As variáveis não podem ter espaços ao redor do `=`. |

---

## Adaptação para outros stacks

Se o seu stack Docker tem outra estrutura, ajuste o `.env`:

- **Diretório do stack diferente:** o script espera `/home/$USER/workspace/docker/$DOCKER_VERSION/`. Se for outro, edite a linha `DOCKER_COMPOSE_DIR=...` nos scripts.
- **Container Moodle com outro nome:** o script monta `moodle-local-$CORE_NAME`. Se o seu compose-file usa outro nome, ajuste `CONTAINER_NAME=...` nos scripts.
- **Caminho do Moodle root diferente:** o script espera `/home/moodle/www/local-$CORE_NAME/`. Ajuste `MOODLE_LOCAL_SITE` e `MOODLE_ROOT_IN_CONTAINER` nos scripts se for diferente.
- **Container PHP mais novo (>= 7.x):** o wrapper do Composer 2.2 (passo `ensure_legacy_composer_for_behat_init`) só é necessário em PHP 5.6. Em PHP 7+ pode ser desabilitado/simplificado.
- **Moodle ≥ 3.5:** as auto-correções de `chromeOptions`/`capabilities` no Behat podem não ser aplicáveis. Revise `behat_config` no `config.php` conforme docs do Moodle daquela versão.

Se acabar mantendo um stack diferente, vale considerar parametrizar essas constantes adicionais no `.env` em vez de hardcoded nos scripts.

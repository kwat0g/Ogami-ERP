#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_ID="$$-$(date +%s)"
CONTAINER="ogami-spa-fallback-test-${TEST_ID}"
NETWORK="ogami-spa-fallback-test-${TEST_ID}"
WORK_DIR="$(mktemp -d)"

cleanup() {
    docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

command -v docker >/dev/null || { echo 'docker is required.' >&2; exit 2; }
command -v openssl >/dev/null || { echo 'openssl is required.' >&2; exit 2; }
command -v curl >/dev/null || { echo 'curl is required.' >&2; exit 2; }
docker image inspect nginx:1.27-alpine >/dev/null 2>&1 || {
    echo 'nginx:1.27-alpine must already be available locally.' >&2
    exit 2
}

mkdir -p "$WORK_DIR/certs/live/ogamiph.dev" "$WORK_DIR/spa"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
    -subj /CN=ogamiph.dev \
    -keyout "$WORK_DIR/certs/live/ogamiph.dev/privkey.pem" \
    -out "$WORK_DIR/certs/live/ogamiph.dev/fullchain.pem" \
    >/dev/null 2>&1
printf '%s\n' '<!doctype html><title>fallback-test-app</title><body>fallback-test-app</body>' \
    > "$WORK_DIR/spa/index.html"

docker network create "$NETWORK" >/dev/null
docker run --detach --rm \
    --name "$CONTAINER" \
    --network "$NETWORK" \
    --publish 127.0.0.1::443 \
    --env SERVER_NAME=ogamiph.dev \
    --volume "$REPO_DIR/docker/nginx/prod-live-dev.conf:/etc/nginx/templates/default.conf.template:ro" \
    --volume "$REPO_DIR/docker/nginx/security-headers-prod.conf:/etc/nginx/security-headers-prod.conf:ro" \
    --volume "$WORK_DIR/certs:/etc/letsencrypt:ro" \
    --volume "$WORK_DIR/spa:/var/www/spa:ro" \
    nginx:1.27-alpine >/dev/null

port="$(docker port "$CONTAINER" 443/tcp | awk -F: 'NR == 1 { print $NF }')"
if [[ -z "$port" ]]; then
    echo 'Test Nginx did not publish an HTTPS port.' >&2
    exit 1
fi

if ! status="$(curl --http1.1 --insecure --silent --show-error --max-time 15 \
    --resolve "ogamiph.dev:${port}:127.0.0.1" \
    --output "$WORK_DIR/response.html" \
    --write-out '%{http_code} %{num_redirects}' \
    "https://ogamiph.dev:${port}/")"; then
    docker logs "$CONTAINER" >&2 || true
    exit 1
fi

if [[ "$status" != '200 0' ]] || ! grep -Fq 'fallback-test-app' "$WORK_DIR/response.html"; then
    printf 'Expected static SPA fallback with no redirect; got status/redirects: %s\n' "$status" >&2
    exit 1
fi

printf 'Unavailable live SPA falls back to static index.html with HTTP 200 and no redirect.\n'

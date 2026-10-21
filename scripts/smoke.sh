#!/usr/bin/env bash
set -uo pipefail

cd "$(dirname "$0")/.."

IMAGE=${IMAGE:-marshmallow:prod}
PORT=${PORT:-18081}
BASE="http://localhost:${PORT}"
CONTAINER="marshmallow-smoke-$$"
VOLUME="marshmallow-smoke-data-$$"
HUB="${BASE}/.well-known/mercure?match=urn:marshmallow:text&match=urn:marshmallow:files"
WORK=$(mktemp -d)
FAILURES=0
SSE_PID=

for tool in curl jq docker; do
	command -v "$tool" >/dev/null || { echo "missing tool: $tool" >&2; exit 2; }
done

cleanup() {
	[ -n "$SSE_PID" ] && kill "$SSE_PID" 2>/dev/null
	docker rm -f "$CONTAINER" >/dev/null 2>&1
	docker volume rm "$VOLUME" >/dev/null 2>&1
	rm -f "$WORK"/*
	rmdir "$WORK"
}
trap cleanup EXIT

pass() { printf '  \033[32mok\033[0m   %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; FAILURES=$((FAILURES + 1)); }
check() {
	local label=$1; shift
	if "$@"; then pass "$label"; else fail "$label"; fi
}
section() { printf '\n== %s\n' "$1"; }

header_of() {
	grep -i "^$2:" "$1" | head -n1 | cut -d: -f2- | sed 's/^ *//' | tr -d '\r'
}
contains() { [[ "$1" == *"$2"* ]]; }
status_is() { [ "$1" = "$2" ]; }

wait_healthy() {
	local status
	for _ in $(seq 1 45); do
		status=$(docker inspect -f '{{.State.Health.Status}}' "$CONTAINER" 2>/dev/null)
		[ "$status" = healthy ] && return 0
		sleep 2
	done
	docker logs --tail 30 "$CONTAINER" >&2
	return 1
}

start_subscriber() {
	curl -sN --max-time 60 "${HUB}${2:-}" >"$1" &
	SSE_PID=$!
	sleep 1
}
stop_subscriber() { kill "$SSE_PID" 2>/dev/null; wait "$SSE_PID" 2>/dev/null; SSE_PID=; }

wait_for() {
	for _ in $(seq 1 50); do
		grep -qF -- "$2" "$1" && return 0
		sleep 0.1
	done
	return 1
}

api() {
	local method=$1 path=$2; shift 2
	curl -s -X "$method" -H 'Accept: application/json' -D "$WORK/headers" -o "$WORK/body" -w '%{http_code}' "$@" "${BASE}${path}"
}
current_version() { curl -s -H 'Accept: application/json' "${BASE}/api/text" | jq -r '.version'; }
text_body() {
	jq -cn --arg c "$1" --argjson v "${2:-$(current_version)}" '{content: $c, baseVersion: $v}'
}
put_text() { api PUT /api/text -H 'Content-Type: application/json' --data-binary "$(text_body "$@")"; }
upload() { api POST /api/files -F "file=@$1"; }

page() {
	curl -s -H 'Accept: text/html' -D "$WORK/headers" -o "$WORK/page" -w '%{http_code}' "${BASE}$1"
}
page_props() {
	page / >/dev/null
	grep -o 'data-symfony--ux-vue--vue-props-value="[^"]*"' "$WORK/page" | sed 's/^[^"]*"//; s/"$//' |
		docker exec -i "$CONTAINER" php -r 'echo html_entity_decode(stream_get_contents(STDIN), ENT_QUOTES | ENT_HTML5, "UTF-8");'
}
props_match_api() {
	local props
	props=$(page_props)
	[ "$(jq -c '.initialText' <<<"$props")" = "$(curl -s -H 'Accept: application/json' "${BASE}/api/text" | jq -c .)" ] &&
		[ "$(jq -c '.initialLatestFile' <<<"$props")" = "$(curl -s -H 'Accept: application/json' "${BASE}/api/files/latest" | jq -c '.[0]')" ]
}

if [ "${BUILD:-0}" = 1 ]; then
	section "build $IMAGE"
	docker build --target prod -t "$IMAGE" . || exit 1
fi

section "run $IMAGE on :$PORT"
docker run -d --name "$CONTAINER" -p "${PORT}:80" -e APP_SECRET="smoke-$$-secret" \
	-e TRUSTED_PROXIES=REMOTE_ADDR -e 'TRUSTED_HOSTS=^(localhost|marshmallow\.test)$' \
	-v "${VOLUME}:/data" "$IMAGE" >/dev/null || exit 1
check "container healthy" wait_healthy
check "runs as www-data" test "$(docker exec "$CONTAINER" id -un)" = www-data

section "page (Symfony + Vue)"
code=$(page /)
check "GET / -> 200 rendered by PHP with the Vue root" bash -c "[ $code = 200 ] && grep -q 'data-controller=\"symfony--ux-vue--vue\"' '$WORK/page'"
check "page mounts the App component" grep -q 'data-symfony--ux-vue--vue-component-value="App"' "$WORK/page"
csp=$(header_of "$WORK/headers" Content-Security-Policy)
check "page CSP: script-src 'self'" contains "$csp" "script-src 'self'"
check "page CSP: style-src allows Google Fonts CSS" contains "$csp" "style-src 'self' https://fonts.googleapis.com"
check "page CSP: font-src allows Google Fonts files" contains "$csp" "font-src 'self' https://fonts.gstatic.com"
check "page CSP: connect-src 'self' (API, Mercure)" contains "$csp" "connect-src 'self'"
check "page CSP: frame-ancestors 'none'" contains "$csp" "frame-ancestors 'none'"
check "page X-Frame-Options DENY" test "$(header_of "$WORK/headers" X-Frame-Options)" = DENY
check "page Cache-Control no-cache" contains "$(header_of "$WORK/headers" Cache-Control)" "no-cache"
asset=$(grep -o '/build/assets/[^"]*\.js' "$WORK/page" | head -n1)
code=$(curl -s -D "$WORK/headers" -o /dev/null -w '%{http_code}' "${BASE}${asset:-/build/assets/missing.js}")
check "GET /build asset ${asset:-<none in page>} -> 200" status_is "$code" 200
check "/build asset immutable" contains "$(header_of "$WORK/headers" Cache-Control)" "immutable"
check "/build/.vite metadata hidden -> 404" status_is "$(curl -s -o /dev/null -w '%{http_code}' "${BASE}/build/.vite/entrypoints.json")" 404
check "GET /favicon.svg -> 200" status_is "$(curl -s -o /dev/null -w '%{http_code}' "${BASE}/favicon.svg")" 200
props=$(page_props)
check "props: initial text and latest file = API" props_match_api
check "props: no latest file yet" test "$(jq -c '.initialLatestFile' <<<"$props")" = null
check "props: limits" jq -e '.limits == {maxUploadBytes: 104857600, maxTextBytes: 524288}' <<<"$props" >/dev/null

section "API docs disabled in prod"
check "GET /api/docs.jsonopenapi -> 404" status_is "$(api GET /api/docs.jsonopenapi)" 404
check "GET /api/docs (html) -> 404" status_is "$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: text/html' "${BASE}/api/docs")" 404

section "text + Mercure"
check "GET /api/text -> 200" status_is "$(api GET /api/text)" 200
check "GET /api/text has version" jq -e '.version | numbers' "$WORK/body" >/dev/null
start_subscriber "$WORK/sse"
first_text="smoke-first-$$"
check "PUT /api/text -> 200" status_is "$(put_text "$first_text")" 200
check "SSE text event received" wait_for "$WORK/sse" "$first_text"
check "SSE event typed 'text'" grep -q '^event: text' "$WORK/sse"
second_text="smoke-second-$$"
check "PUT /api/text (2nd) -> 200" status_is "$(put_text "$second_text")" 200
check "SSE 2nd text event received" wait_for "$WORK/sse" "$second_text"
check "PUT /api/text on a stale baseVersion -> 409" status_is "$(put_text "smoke-stale-$$" 1)" 409
check "409 is a TEXT_CONFLICT problem" status_is "$(jq -r '.type' "$WORK/body" 2>/dev/null)" urn:marshmallow:error:TEXT_CONFLICT
head -c 524289 /dev/zero | tr '\0' a | jq -Rsc --argjson v "$(current_version)" '{content: ., baseVersion: $v}' >"$WORK/oversized.json"
check "PUT 512 KiB + 1 byte -> 422" status_is "$(api PUT /api/text -H 'Content-Type: application/json' --data-binary @"$WORK/oversized.json")" 422
check "422 is a violation on content" status_is "$(jq -c '[.violations[].propertyPath]' "$WORK/body" 2>/dev/null)" '["content"]'
check "PUT 5 MB body -> 413 (Caddy limit)" status_is "$(head -c 5242880 /dev/zero | tr '\0' a | api PUT /api/text -H 'Content-Type: application/json' --data-binary @-)" 413

section "files"
printf '\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x35\x81\x84\x00\x00\x00\x00IEND\xaeB`\x82' >"$WORK/pixel.png"
printf '<!doctype html><script>alert(1)</script>\n' >"$WORK/page.html"
head -c 10485760 /dev/urandom >"$WORK/big.bin"

code=$(upload "$WORK/pixel.png")
check "POST /api/files png -> 201" status_is "$code" 201
png_url=$(jq -r '.url // empty' "$WORK/body" 2>/dev/null)
png_id=$(jq -r '.id // empty' "$WORK/body" 2>/dev/null)
check "SSE file event received" wait_for "$WORK/sse" "${png_id:-<no id>}"
if [ -n "$png_url" ]; then
	curl -s -D "$WORK/headers" -o "$WORK/download" "${BASE}${png_url}"
	check "png download: bytes identical" cmp -s "$WORK/pixel.png" "$WORK/download"
	check "png download: nosniff" contains "$(header_of "$WORK/headers" X-Content-Type-Options)" nosniff
	check "png download: CSP sandbox" contains "$(header_of "$WORK/headers" Content-Security-Policy)" sandbox
	check "png download: image/png" contains "$(header_of "$WORK/headers" Content-Type)" image/png
	check "png download: inline" contains "$(header_of "$WORK/headers" Content-Disposition)" inline
else
	fail "png download (no url in upload response)"
fi

code=$(upload "$WORK/page.html")
check "POST /api/files html -> 201" status_is "$code" 201
html_url=$(jq -r '.url // empty' "$WORK/body" 2>/dev/null)
html_id=$(jq -r '.id // empty' "$WORK/body" 2>/dev/null)
if [ -n "$html_url" ]; then
	curl -s -D "$WORK/headers" -o /dev/null "${BASE}${html_url}"
	check "html download: attachment" contains "$(header_of "$WORK/headers" Content-Disposition)" attachment
	check "html download: application/octet-stream" contains "$(header_of "$WORK/headers" Content-Type)" application/octet-stream
	check "html download: CSP sandbox" contains "$(header_of "$WORK/headers" Content-Security-Policy)" sandbox
	check "html download: nosniff" contains "$(header_of "$WORK/headers" X-Content-Type-Options)" nosniff
else
	fail "html download (no url in upload response)"
fi

check "GET /api/files/latest -> 200" status_is "$(api GET /api/files/latest)" 200
check "latest file is the html upload" test "$(jq -r '.[0].id // empty' "$WORK/body" 2>/dev/null)" = "${html_id:-<none>}"
check "POST 10 MiB file -> 201 (above the 4 MB API limit)" status_is "$(upload "$WORK/big.bin")" 201
big_id=$(jq -r '.id // empty' "$WORK/body" 2>/dev/null)
oversized=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: multipart/form-data; boundary=x' -H 'Content-Length: 110000000' --data-binary x "${BASE}/api/files")
check "upload declaring > 101 MiB -> 413 before PHP" status_is "$oversized" 413
stop_subscriber
check "props: initial text and latest file = API after the uploads" props_match_api
check "props: latest file is the last upload" test "$(page_props | jq -r '.initialLatestFile.id // empty')" = "${big_id:-<none>}"

section "Mercure history"
start_subscriber "$WORK/replay" "&lastEventID=earliest"
sleep 2
stop_subscriber
check "lastEventID=earliest replays at most one text version" test "$(grep -c '^event: text' "$WORK/replay")" -le 1
check "lastEventID=earliest does not replay the first text" bash -c "! grep -qF '$first_text' '$WORK/replay'"

section "reverse proxy + trusted hosts"
proxied_put() {
	api PUT /api/text -H 'Host: marshmallow.test' -H 'Origin: https://marshmallow.test' \
		-H 'Content-Type: application/json' --data-binary "$(text_body 'via proxy')" "$@"
}
check "PUT via trusted proxy (X-Forwarded-Proto https) -> 200" status_is "$(proxied_put -H 'X-Forwarded-Proto: https')" 200
check "PUT https Origin without forwarded proto -> 403" status_is "$(proxied_put)" 403
check "GET with an untrusted Host -> 400" status_is "$(api GET /api/text -H 'Host: rebound.example')" 400
check "PUT /api/text restores the 2nd text" status_is "$(put_text "$second_text")" 200

section "restart + persistence"
docker restart "$CONTAINER" >/dev/null
check "healthy after restart" wait_healthy
check "GET /api/text -> 200" status_is "$(api GET /api/text)" 200
check "text persisted" test "$(jq -r '.content' "$WORK/body" 2>/dev/null)" = "$second_text"
check "GET /api/files/latest -> 200" status_is "$(api GET /api/files/latest)" 200
check "latest file persisted" test "$(jq -r '.[0].id // empty' "$WORK/body" 2>/dev/null)" = "${big_id:-<none>}"
if [ -n "$png_url" ]; then
	curl -s -o "$WORK/download" "${BASE}${png_url}"
	check "png bytes persisted" cmp -s "$WORK/pixel.png" "$WORK/download"
fi

printf '\n'
if [ "$FAILURES" -gt 0 ]; then
	echo "smoke: $FAILURES failure(s)"
	exit 1
fi
echo "smoke: all checks passed"

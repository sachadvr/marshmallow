.PHONY: up contract

up:
	docker compose up -d --build

contract:
	docker compose run --rm -T app bin/console api:openapi:export > assets/api/generated/openapi.json
	bun run api:types

.PHONY: build-prod up-prod smoke

build-prod:
	docker build --target prod -t marshmallow:prod .

up-prod:
	docker compose -f compose.prod.yaml up -d --build

smoke: build-prod
	scripts/smoke.sh

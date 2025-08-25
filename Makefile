## Makefile: Docker-first workflow (build once, then run everything inside Docker)

SHELL := /bin/bash
COMPOSE ?= docker compose

# Internal wrappers (do not call directly)
define RUN
$(COMPOSE) run --rm -e COMPOSER_CACHE_DIR=/tmp/composer-cache app sh -lc $(1)
endef


.DEFAULT_GOAL := help

.PHONY: help
help:
	@echo "Targets:"
	@echo "  docker-build        Build the Docker environment (images)"
	@echo "  docker-ps           Show Docker Compose services status"
	@echo "  docker-down         Stop and remove containers/volumes"
	@echo "  shell               Open a shell inside the app container"
	@echo "  install             composer install inside Docker"
	@echo "  test                Run all tests inside Docker"
	@echo "  test-unit           Run unit tests inside Docker"
	@echo "  test-integration    Run sandbox tests inside Docker (optional: CERT=/host/path/cert.p12)"
	@echo "  coverage            Generate coverage report inside Docker"
	@echo "  phpstan             Run PHPStan inside Docker"
	@echo "  quality             Run style, static analysis, and tests inside Docker"
	@echo "  composer ARGS=...   Run arbitrary composer command inside Docker"
	@echo "  clean               Clean build artifacts inside Docker"

# --- Docker environment ---
.PHONY: docker-build docker-ps docker-down shell
docker-build:
	$(COMPOSE) build
.PHONY: docker-up
docker-up:
	$(COMPOSE) up -d
docker-ps:
	$(COMPOSE) ps

docker-down:
	$(COMPOSE) down -v --remove-orphans

shell:
	$(COMPOSE) run --rm app sh

# --- Project commands (inside Docker) ---
.PHONY: install test test-unit test-integration coverage phpstan quality composer clean
install:
	$(call RUN,"composer install --no-interaction --prefer-dist")

test:
	$(call RUN,"composer install --no-interaction --prefer-dist && composer test")

test-unit:
	$(call RUN,"composer install --no-interaction --prefer-dist && composer test-unit")

test-integration:
	@EXTRA=""; \
	if [ -n "$(CERT)" ]; then \
	  if [ ! -f "$(CERT)" ]; then echo "ERROR: CERT file not found: $(CERT)"; exit 1; fi; \
	  EXTRA="-v $(CERT):/certs/cert.p12:ro"; \
	fi; \
	$(COMPOSE) run --rm $$EXTRA -e COMPOSER_CACHE_DIR=/tmp/composer-cache app sh -lc "composer install --no-interaction --prefer-dist && composer test-sandbox"

coverage:
	$(call RUN,"composer install --no-interaction --prefer-dist && composer coverage")

phpstan:
	$(call RUN,"composer install --no-interaction --prefer-dist && composer phpstan")

quality:
	$(call RUN,"composer install --no-interaction --prefer-dist && composer quality")

composer:
	$(call RUN,"composer $(ARGS)")

clean:
	$(call RUN,"rm -rf var/coverage/html .phpunit.cache || true")

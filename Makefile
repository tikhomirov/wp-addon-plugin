.PHONY: help test quality release

help:
	@echo "Targets:"
	@echo "  make test      — run unit tests (composer test)"
	@echo "  make quality   — lint, PHPStan, tests"
	@echo "  make release   — build dist/wp-addon-plugin-<version>.zip (git archive)"

test:
	composer test

quality:
	composer quality

release:
	bash scripts/build-release.sh
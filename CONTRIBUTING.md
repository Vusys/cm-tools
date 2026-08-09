# Contributing

## Quick start

```bash
composer install
composer test            # PHPUnit
composer check            # pint:check -> rector:check -> analyse (level 9, no baseline), halts on first failure
```

## Building the standalone binary

The published binary is a single-file `.phar` built with [Box](https://github.com/box-project/box). Box is deliberately **not** a Composer dependency of this package — see [CLAUDE.md](CLAUDE.md) for why — so install it globally once:

```bash
composer global require humbug/box
```

Then, from a clean `--no-dev` install:

```bash
composer install --no-dev
box compile   # or: ~/.config/composer/vendor/bin/box compile if the global bin dir isn't on PATH
composer install   # restore dev dependencies afterwards
php builds/cm-tools.phar --version
```

CI's `release.yml` does this automatically on every published GitHub release.

## Before opening a PR

- `composer check` and `composer test` must pass locally.
- If you touched `src/Reflow/*`, add a fixture covering the new construct — round-trip tests (wrap then unwrap recovers a canonical form) catch far more than exact-output assertions.
- If you touched `src/Dialect/*`, add a case to the relevant `RuleSet` test showing what it flags and what it allows.

## Pre-1.0

cm-tools is pre-1.0. Breaking changes are acceptable when called out clearly in the pull request description and in the [changelog](CHANGELOG.md) — there's no deprecation cycle to honor yet.

## Changelog

Add an entry under the `## [Unreleased]` heading in [CHANGELOG.md](CHANGELOG.md), grouped under `### Added` / `### Changed` / `### Fixed` per [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Describe the change from a consumer's point of view, not the implementation.

## Architecture

[CLAUDE.md](CLAUDE.md) is a short, code-navigation-oriented map of how the package fits together.

# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased](https://github.com/Vusys/cm-tools/compare/v0.1.0...HEAD)

### Added

- `DocumentParser::environment()` exposes the shared CommonMark environment, so callers that need a renderer rather than a parser get the identical extension set instead of assembling a copy that can drift.

## [0.1.0](https://github.com/Vusys/cm-tools/releases/tag/v0.1.0) - 2026-08-09

### Added

- Initial scaffold: `wrap`/`unwrap` fixed-width reflow, `lint`/`detect` dialect validation against strict CommonMark, GitHub Flavored Markdown, and JIRA Cloud markdown.
- Symfony Console-based CLI, shipped as a standalone `.phar` built with Box.

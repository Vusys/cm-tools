# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased](https://github.com/Vusys/cm-tools/compare/v0.1.0...HEAD)

### Added

- `DocumentParser::environment()` exposes the shared CommonMark environment, so callers that need a renderer rather than a parser get the identical extension set instead of assembling a copy that can drift.

### Fixed

- A blank line inside a blockquote keeps its `>` marker. Previously it was emitted bare, which ends a quote — so a single quote containing several paragraphs (or a code block, list, or nested quote) came back out as several separate quotes. ([#5](https://github.com/Vusys/cm-tools/issues/5))
- A literal U+0001 in the source is no longer turned into a hard line break. That byte is the in-band sentinel `wrap`/`unwrap` use to mark a real hard break, so an occurrence in the document was indistinguishable from one. It is now removed from the input before parsing, which makes the sentinel unambiguous by construction — note this means the byte is dropped rather than preserved. ([#9](https://github.com/Vusys/cm-tools/issues/9))
- Wrapping no longer turns prose into block markup. A line break that pushed a `-`, `+`, `#`, `1.`, `1)`, or a `---`/`===` run to the start of a line silently reinterpreted the text as a list, heading, or setext underline — and since it depended on where the break landed, the same document could round-trip cleanly at `--width=80` and corrupt at 79. Such a marker is now escaped at line start. This also covers markers the author escaped in the source, which were previously unescaped on output at every width. ([#6](https://github.com/Vusys/cm-tools/issues/6))
- A tight list containing a nested sublist stays tight. Previously a blank line was emitted before the sublist, which promoted the whole list to loose — wrapping every item's content in a `<p>`, including siblings that had no sublist at all. ([#7](https://github.com/Vusys/cm-tools/issues/7))
- An empty list item keeps its marker instead of being dropped. Previously the item disappeared, leaving a stray blank line, and an ordered list renumbered around the gap so the surviving items silently changed number. ([#8](https://github.com/Vusys/cm-tools/issues/8))

## [0.1.0](https://github.com/Vusys/cm-tools/releases/tag/v0.1.0) - 2026-08-09

### Added

- Initial scaffold: `wrap`/`unwrap` fixed-width reflow, `lint`/`detect` dialect validation against strict CommonMark, GitHub Flavored Markdown, and JIRA Cloud markdown.
- Symfony Console-based CLI, shipped as a standalone `.phar` built with Box.

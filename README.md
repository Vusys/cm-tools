# cm-tools

CommonMark tools, shipped as a single binary.

- **Fixed-width reflow** — wrap a document's prose to a column width, or collapse it back to
  one line per paragraph.
- **Dialect validation** — check a document against a target Markdown dialect (strict
  CommonMark, GitHub, JIRA Cloud) and report constructs it doesn't support.

## Install

Download the latest `cm-tools` binary from the
[releases page](https://github.com/Vusys/cm-tools/releases) and put it on your `PATH`:

```bash
chmod +x cm-tools
mv cm-tools /usr/local/bin/
```

Or, as a Composer dependency:

```bash
composer global require vusys/cm-tools
```

## Usage

```bash
# Wrap to 80 columns (the default)
cm-tools wrap README.md
cm-tools wrap README.md --width=100 --output=README.wrapped.md

# Collapse back to one line per paragraph
cm-tools unwrap README.wrapped.md

# Check against a dialect — exits non-zero if anything doesn't fit
cm-tools lint notes.md --dialect=jira-cloud
cm-tools lint notes.md --dialect=github     # the default

# Report which known dialects a document is compatible with
cm-tools detect notes.md
```

All four commands read from stdin and write to stdout when no file/`--output` is given, so
they compose:

```bash
cm-tools wrap --width=72 < draft.md | cm-tools lint --dialect=jira-cloud -
```

Run `cm-tools <command> --help` for the full option list.

## Dialects

| Key | What it models |
|---|---|
| `commonmark` | The base CommonMark spec — no GFM extensions, no footnotes. Renders identically anywhere CommonMark is supported. |
| `github` | GitHub Flavored Markdown as rendered on github.com — tables, strikethrough, task lists, autolinks, footnotes. Flags the small set of raw-HTML tags GitHub strips/escapes. |
| `jira-cloud` | The constrained Markdown-shortcut syntax JIRA Cloud's editor accepts in descriptions/comments — not classic JIRA wiki markup. Best-effort baseline; expect it to grow as real documents surface gaps. |

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for the local dev workflow, and [CLAUDE.md](CLAUDE.md)
for a map of how the package fits together.

## License

MIT — see [LICENSE](LICENSE).

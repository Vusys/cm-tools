---
name: cm-tools
description: Reflow Markdown to a fixed column width, or check that a Markdown file will render on a target — strict CommonMark, GitHub, or a JIRA Cloud issue description. Use when wrapping or unwrapping prose in .md files, when a document has to paste cleanly into JIRA or GitHub, or when the user mentions cm-tools, wrap, unwrap, lint, or detect in a Markdown context.
---

# cm-tools

One binary. Four commands work on a document: `wrap`, `unwrap`, `lint`, `detect`. Every one
takes a file argument, or reads stdin when the argument is omitted or is `-`; every one writes
to stdout unless given `--output`. So they compose:
`cm-tools wrap --width=72 < draft.md | cm-tools lint --dialect=jira-cloud -`.

## Reflowing prose

```bash
cm-tools wrap docs/guide.md                                    # 80 columns, to stdout
cm-tools wrap docs/guide.md --width=100 --output=docs/guide.md # rewrite in place
cm-tools unwrap docs/guide.md                                  # collapse to one line per block
```

Prefer this over hand-wrapping Markdown. `wrap` re-serialises the parsed document, so it is a
normaliser as much as a wrapper — nested list and blockquote indentation comes out consistent,
which is exactly the part that goes wrong when reflowing by eye.

Pointing `--output` at the input file is safe: the input is read completely before anything is
written.

## Checking a dialect

```bash
cm-tools lint notes.md --dialect=jira-cloud
cm-tools lint notes.md                        # --dialect=github is the default
cm-tools detect notes.md
```

`lint` exits **1** when the document uses something the dialect does not support, printing one
line per violation:

```
notes.md:12: [table] GFM table syntax is not supported by JIRA Cloud markdown.
```

That makes it usable as a gate before writing a description or comment somewhere. On success it
prints `cm-tools: compatible with <dialect>` and exits 0.

`detect` reports instead of enforcing — every known dialect with `compatible` or
`N violation(s)`, always exit 0. Use it when the target is not yet decided:

```
CommonMark (strict)        2 violation(s)
GitHub Flavored Markdown   compatible
JIRA Cloud markdown        2 violation(s)
```

## What each dialect rejects

The bracketed key in `lint` output is one of these rows.

| Construct | `commonmark` | `github` | `jira-cloud` |
|---|---|---|---|
| `table` | ✗ | ✓ | ✗ |
| `strikethrough` | ✗ | ✓ | ✗ |
| `task-list` | ✗ | ✓ | ✗ |
| `footnote` | ✗ | ✓ | ✗ |
| `html-block`, `html-inline` | ✓ | ✓ except stripped tags † | ✗ |

† GitHub strips or escapes `script`, `style`, `iframe`, `title`, `textarea`, `xmp`, `noembed`,
`noframes`, `plaintext`. These parse fine and are flagged anyway, because they will not render
as authored.

`jira-cloud` models the Markdown shortcuts JIRA Cloud's rich-text editor accepts while typing
into a description or comment. It is **not** classic JIRA wiki markup (`h1.`, `{code}`,
`||table||`) — cm-tools neither produces nor validates that.

## Things that trip people up

- **Parsing is deliberately permissive.** Every document is parsed as CommonMark + GFM +
  footnotes whatever `--dialect` says; the dialect is applied afterwards by walking the AST. A
  `lint` failure therefore never means "this file is malformed" — it means "this construct will
  not survive the target". Do not go hunting for a syntax error.
- **`lint` has no fixer.** Rewrite the flagged construct into something the target renders: a
  table becomes a bulleted list or a code block, `~~text~~` becomes plain text, a footnote
  becomes an inline parenthetical.
- **Hard line breaks are two trailing spaces**, and `wrap`/`unwrap` preserve them as such. If
  you post-process the output, do not strip trailing whitespace — a bare newline re-parses as a
  soft break and the line break silently disappears.
- A non-numeric `--width` falls back to 80 rather than erroring; `--width=0` is rejected.

# CLAUDE.md

Architecture notes for an AI assistant working in this repo. This is a map, not a spec — it's for finding the "where" quickly.

## What this package does

Two independent CommonMark tools behind one binary:

1. **Fixed-width reflow** (`wrap`/`unwrap`) — re-serialise a parsed document to CommonMark source, either wrapped to a column width or collapsed to one line per block.
2. **Dialect validation** (`lint`/`detect`) — walk the same parsed AST and report which constructs a target dialect (strict CommonMark, GitHub, JIRA Cloud) doesn't support.

Both features share one parser configuration (`Support\DocumentParser`) — core CommonMark + GFM + footnotes, always. Parsing is deliberately permissive; dialect *restrictions* are enforced afterwards by walking the AST, not by refusing to parse.

## Where things live

| Concern                                                                                  | File                                                   |
| ---------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| Shared parser (core + GFM + footnotes)                                                   | `src/Support/DocumentParser.php`                       |
| Re-serialise inline AST nodes to markdown text                                           | `src/Reflow/InlineRenderer.php`                        |
| Split rendered text into break-safe atoms (code spans, autolinks, link tails stay whole) | `src/Reflow/Tokenizer.php`                             |
| Greedy line packing at a given width (or unlimited)                                      | `src/Reflow/LinePacker.php`                            |
| Escape prose that a line break would turn into block markup (`-`, `#`, `1.`, `---`)      | `src/Reflow/LineStartEscaper.php`                      |
| Walk the block tree, thread indent width through nested blockquotes/lists                | `src/Reflow/BlockRenderer.php`                         |
| Public `wrap`/`unwrap` entry points                                                      | `src/Reflow/Wrapper.php`, `src/Reflow/Unwrapper.php`   |
| Declarative "what this dialect doesn't support"                                          | `src/Dialect/RuleSet.php`, `src/Dialect/RuleSet/*.php` |
| AST walk that checks node types against a rule set                                       | `src/Dialect/Validator.php`                            |
| `--dialect=` name → `RuleSet` lookup                                                     | `src/Dialect/RuleSetRegistry.php`                      |
| `wrap`/`unwrap`/`lint`/`detect`/`install-skill` commands                                 | `src/Console/Command/*.php`                            |
| Claude Code skill written by `install-skill`                                             | `resources/skill/SKILL.md`                             |
| Locating that asset, in-repo and inside the phar                                         | `src/Support/SkillAsset.php`                           |
| Reflow test catalogue — input plus expected wrap/unwrap output, one file per case        | `tests/Fixtures/Reflow/` (see its README)              |
| `mixed` → typed input without `@var` overrides (see below)                               | `src/Console/TypedInput.php`                           |

## Reflow model, in one paragraph

`InlineRenderer` re-serialises an inline AST subtree to a markdown *string*, gluing each delimiter (`*`, `**`, `~~`, `[...]`) directly onto its content with no inserted whitespace — correct markdown requires that adjacency anyway, so it's free. Wrapping/unwrapping then happens at the *string* level, not the AST level: `Tokenizer` splits that string into atoms, treating backtick code spans, `<...>` autolinks/raw-HTML, and a link/image's `](url "title")` tail as unbreakable; `LinePacker` greedily packs atoms into lines (or one line, for unwrap). This decouples "turn AST into markdown" from "decide where lines may break", so either can change independently. `BlockRenderer` threads a cumulative `$indent` string down through nested blockquotes/lists purely so a nested paragraph knows how much width its ancestors' `"> "`/`"  "` prefixes will consume — the prefixes themselves are applied once, by the immediate parent. A hard line break (trailing double-space) must round-trip as *two trailing spaces* on the output line, not just a bare newline — a bare newline re-parses as a soft break, silently changing meaning. Hard breaks travel through the rendered string as an in-band `\x01` sentinel, which only works because `InlineRenderer::withoutSentinels()` strips that byte from the source at the two Reflow entry points — so any literal occurrence is gone before it can be mistaken for a marker. Finally, `LineStartEscaper` runs over each packed line *before* the parent applies its `"> "`/`"  "` prefix: whether text like `- echo` is prose or a list item depends on where the break landed, so it can only be decided after packing, and a line that is about to be prefixed needs the check just as much as a top-level one.

Correctness for all of the above is pinned by `tests/Fixtures/Reflow` rather than by assertions in PHP string literals. Beyond matching expected output, every case there is checked to be idempotent under a second pass and to render to the same HTML as its input — that second property is what catches "the output looks plausible but parses as a different document", which is how essentially every reflow bug has presented.

## Dialect model, in one paragraph

Every `RuleSet` is pure data: `unsupportedConstructs()` returns a subset of `Validator`'s `CONSTRUCT_*`-equivalent constants (`TABLE`, `STRIKETHROUGH`, `TASK_LIST`, `FOOTNOTE`, `HTML_BLOCK`, `HTML_INLINE`), and `disallowedHtmlTags()` optionally names raw-HTML tags that parse fine but get stripped/escaped by that target (GitHub's `DisallowedRawHtmlExtension` list). `Validator` does the one AST walk all three rule sets share, matching node type to construct key. Adding a fourth dialect means writing a `RuleSet` implementation, not another walker.

## Console layer

Symfony Console (`src/Console/Command/*.php`), **not** the hand-rolled dispatcher zefram/tetryon use — cm-tools ships as a binary for direct end-user CLI use, where per-command `--help`, option validation, and colorized output earn their keep in a way they don't for an internal 2-command dev tool. `TypedInput` (`stringArgument`/`stringOption`/ `intOption`/`boolOption`) exists because `InputInterface::getArgument()`/`getOption()` return `mixed` — narrow with a real `is_string`/`is_numeric` check, not a `@var` override or `assert()`, so PHPStan traces the actual control flow.

## The binary

`box.json` builds a single `.phar` via [Box](https://github.com/box-project/box). Box is **not** a Composer dependency of this package — see `release.yml`'s comment and `.coderabbit.yaml` for why (it `replace`s `symfony/polyfill-php80` etc., which lets Composer's solver satisfy `league/commonmark`'s real dependency *through* Box and leak it into the non-dev package set if Box is required at all). Install it globally (`composer global require humbug/box`) instead.

The phar is no longer source-only: `install-skill` reads `resources/skill/SKILL.md` at runtime, so `box.json`'s `directories` has to list `resources` — a test asserts it does, because omitting it breaks only the built binary and nothing catches that in-repo. `SkillAsset::path()` builds the path with `dirname(__DIR__, 2)` rather than a `..` suffix so it resolves under the `phar://` stream wrapper too.

## The shipped skill

`resources/skill/SKILL.md` is the Claude Code skill `install-skill` writes out — a real Markdown file, not a heredoc, so it diffs and reviews as prose. Its content is otherwise unchecked, with two exceptions `tests/Unit/Support/SkillAssetTest.php` enforces: the frontmatter must parse with a `name` matching the install directory, and the dialect table must agree cell-for-cell with what `RuleSetRegistry` and each `RuleSet` declare. That second check is the whole reason the table can stay in the asset instead of being rendered from the registry at write time — a new dialect or construct fails the suite rather than silently leaving the skill stale.

## Running the suites

```bash
composer test        # PHPUnit
composer check        # pint:check -> rector:check -> analyse (level 9, Larastan loaded), halts on first failure
```

## Further reading

- [CONTRIBUTING.md](CONTRIBUTING.md) — contributor quick-start, including building the phar.

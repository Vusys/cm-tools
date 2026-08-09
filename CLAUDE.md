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
| Walk the block tree, thread indent width through nested blockquotes/lists                | `src/Reflow/BlockRenderer.php`                         |
| Public `wrap`/`unwrap` entry points                                                      | `src/Reflow/Wrapper.php`, `src/Reflow/Unwrapper.php`   |
| Declarative "what this dialect doesn't support"                                          | `src/Dialect/RuleSet.php`, `src/Dialect/RuleSet/*.php` |
| AST walk that checks node types against a rule set                                       | `src/Dialect/Validator.php`                            |
| `--dialect=` name → `RuleSet` lookup                                                     | `src/Dialect/RuleSetRegistry.php`                      |
| `wrap`/`unwrap`/`lint`/`detect` commands                                                 | `src/Console/Command/*.php`                            |
| `mixed` → typed input without `@var` overrides (see below)                               | `src/Console/TypedInput.php`                           |

## Reflow model, in one paragraph

`InlineRenderer` re-serialises an inline AST subtree to a markdown *string*, gluing each delimiter (`*`, `**`, `~~`, `[...]`) directly onto its content with no inserted whitespace — correct markdown requires that adjacency anyway, so it's free. Wrapping/unwrapping then happens at the *string* level, not the AST level: `Tokenizer` splits that string into atoms, treating backtick code spans, `<...>` autolinks/raw-HTML, and a link/image's `](url "title")` tail as unbreakable; `LinePacker` greedily packs atoms into lines (or one line, for unwrap). This decouples "turn AST into markdown" from "decide where lines may break", so either can change independently. `BlockRenderer` threads a cumulative `$indent` string down through nested blockquotes/lists purely so a nested paragraph knows how much width its ancestors' `"> "`/`"  "` prefixes will consume — the prefixes themselves are applied once, by the immediate parent. A hard line break (trailing double-space) must round-trip as *two trailing spaces* on the output line, not just a bare newline — a bare newline re-parses as a soft break, silently changing meaning.

## Dialect model, in one paragraph

Every `RuleSet` is pure data: `unsupportedConstructs()` returns a subset of `Validator`'s `CONSTRUCT_*`-equivalent constants (`TABLE`, `STRIKETHROUGH`, `TASK_LIST`, `FOOTNOTE`, `HTML_BLOCK`, `HTML_INLINE`), and `disallowedHtmlTags()` optionally names raw-HTML tags that parse fine but get stripped/escaped by that target (GitHub's `DisallowedRawHtmlExtension` list). `Validator` does the one AST walk all three rule sets share, matching node type to construct key. Adding a fourth dialect means writing a `RuleSet` implementation, not another walker.

## Console layer

Symfony Console (`src/Console/Command/*.php`), **not** the hand-rolled dispatcher zefram/tetryon use — cm-tools ships as a binary for direct end-user CLI use, where per-command `--help`, option validation, and colorized output earn their keep in a way they don't for an internal 2-command dev tool. `TypedInput` (`stringArgument`/`stringOption`/ `intOption`) exists because `InputInterface::getArgument()`/`getOption()` return `mixed` — narrow with a real `is_string`/`is_numeric` check, not a `@var` override or `assert()`, so PHPStan traces the actual control flow.

## The binary

`box.json` builds a single `.phar` via [Box](https://github.com/box-project/box). Box is **not** a Composer dependency of this package — see `release.yml`'s comment and `.coderabbit.yaml` for why (it `replace`s `symfony/polyfill-php80` etc., which lets Composer's solver satisfy `league/commonmark`'s real dependency *through* Box and leak it into the non-dev package set if Box is required at all). Install it globally (`composer global require humbug/box`) instead.

## Running the suites

```bash
composer test        # PHPUnit
composer check        # pint:check -> rector:check -> analyse (level 9, Larastan loaded), halts on first failure
```

## Further reading

- [CONTRIBUTING.md](CONTRIBUTING.md) — contributor quick-start, including building the phar.

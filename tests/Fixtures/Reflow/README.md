# Reflow fixture catalogue

Each `*.fixture` file in this directory is one reflow case: some markdown, plus exactly what `wrap` and `unwrap` should turn it into. They are loaded by `Tests\Support\ReflowFixture` and run by `Tests\Unit\Reflow\ReflowCatalogueTest`.

The point of keeping them as files rather than as PHP data providers is that the things most likely to break are the things hardest to see in a PHP string literal: a hard break's two trailing spaces, a blank line inside a blockquote that still needs its `>`, an empty list item, a stray control byte. Here they are just markdown, and a failure diff reads as markdown too.

## Format

A fixture is a sequence of sections, each opened by a line starting with `@@@`:

```
@@@ note
Blank lines inside a blockquote must keep their marker, or one quote
becomes several. See #5.

@@@ input
> First paragraph.
>
> Second paragraph.

@@@ wrap 80
> First paragraph.
>
> Second paragraph.

@@@ unwrap
> First paragraph.
>
> Second paragraph.
```

| Section           | Meaning                                                                                    |
| ----------------- | ------------------------------------------------------------------------------------------ |
| `@@@ note`        | Free text for humans. Ignored by the runner — say what the case pins down, and link the issue. |
| `@@@ input`       | The source markdown. Required.                                                             |
| `@@@ wrap <n>`    | Expected output of `wrap(input, n)`. May be repeated at different widths.                  |
| `@@@ unwrap`      | Expected output of `unwrap(input)`.                                                        |
| `@@@ html-drift`  | Opts this case out of the HTML equivalence check (see below). Body is the reason.          |

A fixture must have an `@@@ input` and at least one expectation, or loading it fails — a case that asserts nothing is worse than no case at all.

Blank lines directly around a section body are separators, not content, and are stripped; blank lines *within* a body are kept, as are trailing spaces on a line. (`.editorconfig` disables trailing-whitespace trimming for `*.fixture` for exactly that reason.)

## What every case is checked for

Beyond matching the expectation byte-for-byte, each case is also checked to be:

- **idempotent** — reflowing the output again is a no-op, so a document doesn't drift a little further every time the tool runs over it;
- **meaning-preserving** — the input and the output render to the same HTML, so the reflow only changed the source. Whitespace outside `<pre>`/`<code>` is collapsed before comparing, because re-wrapping a paragraph necessarily moves soft breaks around.

That second check is the one that matters. Nearly every reflow bug filed so far was a case where the output looked plausible but parsed as a different document — a quote split in two, a tight list gone loose, prose that became a heading. An expectation typed by hand can be wrong in the same way; the HTML comparison is an independent oracle for it.

Where a difference is genuinely intended, declare it with `@@@ html-drift` and say why. The runner asserts that such a case *does* still drift, so the marker can't outlive the reason for it.

## Adding a case

Write the `@@@ input`, run the tool to get a candidate output, then **read the output and decide whether it is correct** before pasting it into a `@@@ wrap`/`@@@ unwrap` section. Generating expectations from current behaviour and not reading them just freezes today's bugs into the suite.

Widths are cheap: a bug that only shows up when a break lands in one particular place is invisible at 80 and obvious at 13. If a case is about where lines break, pin it at more than one width.

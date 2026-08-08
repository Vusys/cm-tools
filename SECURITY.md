# Security Policy

cm-tools is a single-maintainer, pre-1.0 project. There is no formal support matrix — the
latest release on the default branch is the only version that receives fixes.

## Reporting a vulnerability

Preferred: use GitHub's private vulnerability reporting for this repository —
<https://github.com/Vusys/cm-tools/security/advisories/new>. This keeps the report private
until a fix is available and lets you track the response through GitHub.

If you can't use that (e.g. you don't have a GitHub account), email
**bryan@vuii.co.uk** with as much detail as you can: affected version, a reproduction case,
and the impact you believe it has.

Please don't open a public issue for a suspected vulnerability.

## Scope

**In scope:**

- `src/Reflow/*` — the wrap/unwrap engine. Input is a Markdown document supplied by the user
  running the tool; there's no untrusted-network-input surface, but a malformed document
  shouldn't be able to trigger unbounded memory/CPU use.
- `src/Dialect/*` — the validator. A crafted document that causes a false "compatible" result
  isn't a memory-safety issue, but is a correctness issue worth reporting if it has real
  consequences (e.g. content that silently breaks on the target platform).
- `.github/` — secret-leak surface in this repo's own CI/release workflows, particularly
  `release.yml`, which builds and publishes the distributed binary.

**Out of scope:**

- `league/commonmark` itself — report upstream at
  [thephpleague/commonmark](https://github.com/thephpleague/commonmark).
- Composer dependencies in general — report to the dependency's own project.
- Content a user chooses to run cm-tools against — the tool parses and reformats Markdown; it
  doesn't execute it.

## What counts as a security issue here

cm-tools' risk surface is narrow: it reads a file (or stdin) and writes a file (or stdout). In
scope: a path-handling bug in `--output=` that writes outside the intended location, or a
parsing input that causes pathological (non-linear) resource use. General correctness bugs
without a security angle belong in a regular
[GitHub issue](https://github.com/Vusys/cm-tools/issues) instead.

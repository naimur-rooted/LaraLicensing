# Memory Index

Canonical index of every memory file under `.ai-memory/memory/`. Each subfolder holds one file per rule, decision, or pattern. Deep-read on demand; always survey this index first.

## Folders

- `architecture/` — system design decisions.
  - [`01-system-overview.md`](./architecture/01-system-overview.md) - high-level system identity, domain model, and licensing flow overview.
- `avoid/` — session-derived skip rules.
- `constraints/` — non-negotiable rules.
  - [`01-memory-protocol.md`](./constraints/01-memory-protocol.md) - mandatory rules for memory enforcement.

- `decisions/` — recorded decisions with rationale (ADR-style).
- `history/` — historical logs.
  - [`02-fixes-log.md`](./history/02-fixes-log.md) - fix log for reseller sharding, wizard auto-selection, backend openapi, and preview bridges.
- `patterns/` — reusable code and doc patterns.
- `processes/` — how work gets done.
  - [`02-licensing-testing-methods.md`](./processes/02-licensing-testing-methods.md) - protocols and methods for testing licensing features and client simulation.
- `specs/` — verbatim user specs.
- `standards/` — technical standards.
- `style/` — naming, palette, and typography rules.
- `workflow/` — current workflow state.

## Conventions

- One file per entry, kebab-case slug, PascalCase JSON keys when structured.
- New entry: create the file, then add a bullet here with a one-line description.
- Never store secrets, tokens, or per-invocation prompt mirrors.

## Decisions

- [`spec-numbering-scheme.md`](./decisions/spec-numbering-scheme.md) - top-level spec labels and references match on-disk folder prefixes exactly.
- [`api-host-vs-frontend.md`](./decisions/api-host-vs-frontend.md) - LaraLicensingV1 runs as an external Laravel API consumed directly by this TanStack operator UI.

## Standards

- [`no-magic-literals.md`](./standards/no-magic-literals.md) - every domain literal comes from a shared enum/const/catalog; cross-tier parity between spec, TS, PHP, and migrations.
- [`preview-is-primary-dev-surface.md`](./standards/preview-is-primary-dev-surface.md) - Preview mode is the primary dev surface; every admin route must render green under `default` and `empty` seeds, `error` is the only seed allowed to surface a scoped `RouteErrorState`.
- [`error-notification-center.md`](./standards/error-notification-center.md) - specifies the global error notification bell and client/server log merging.
- [`05-logging-and-wrapper-rules.md`](./standards/05-logging-and-wrapper-rules.md) - specifies rules for automatic logging query wrappers, enum suffixes, and explicit boolean checks.

## Style

- [`fluid-palette.md`](./style/fluid-palette.md) - canonical OKLCH tokens for light and dark themes, WCAG contrast pairs, and the four guards that block palette drift.

## Workflow

- [`01-session-state.md`](./workflow/01-session-state.md) - active/deferred plan state as of v0.681.0.

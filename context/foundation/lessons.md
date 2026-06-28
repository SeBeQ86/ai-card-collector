# Lessons Learned — AI Card Collector

Append-only. Add entries as they arise; do not edit or remove existing ones.

---

## L-001 — Do not accept partially generated SQL schemas

A schema written with truncated lines (`NOT N`, `DEFAU`, partial constraint names) silently
breaks on import. Always read the full file back after writing it and confirm there are no
cut-off fragments before proceeding.

## L-002 — Do not commit real password hashes in seed files

Seed files are committed to version control. Any bcrypt hash in `database/seed.sql` must be
a clearly labelled placeholder (e.g. `$2y$12$REPLACE_THIS_WITH_OUTPUT_OF_password_hash_FUNCTION`).
Real hashes — even for throwaway passwords — must be generated locally and inserted by hand,
never committed.

## L-003 — Do not add Composer dependencies unless explicitly approved

The project runs without Composer on XAMPP. Adding a `require` entry to `composer.json`
without an explicit task instruction that names the package breaks the no-install constraint
and may silently change the runtime on shared hosting.

## L-004 — public/ is the only intended web root

All page entry points live in `public/`. Config, source, database, context, and doc files
live outside `public/` and must never be reachable via a browser. Apache `.htaccess` rules
provide a secondary safety net, but the primary control is the directory structure itself.

## L-005 — After a certification audit passes, prefer documentation and screenshots over new features

Once the MVP is verified against the certification checklist, the safest next steps are
improving docs, adding demo data, and capturing screenshots. New features introduced after
a passing audit risk breaking verified behaviour and requiring a re-audit.

## L-006 — Agent rule files must reflect the actual codebase, not the intended architecture

`AGENTS.md` and `CLAUDE.md` were initially written based on the planned folder layout
(`app/` for classes, front-controller routing). The real implementation diverged:
classes ended up in `src/`, pages are separate PHP files, not router branches. Stale
rules mislead future agents. After implementation, update rule files to match reality.

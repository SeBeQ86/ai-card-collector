---
bootstrapped_at: 2026-06-24T00:00:00Z
starter_id: plain-php
starter_name: Plain PHP 8.x (no framework)
project_name: ai-card-collector
language_family: php
package_manager: composer
cwd_strategy: native-cwd
bootstrapper_confidence: best-effort
phase_3_status: ok
audit_command: "null"
---

## Hand-off

```yaml
starter_id: plain-php
package_manager: composer
project_name: ai-card-collector
hints:
  language_family: php
  team_size: solo
  deployment_target: shared-hosting
  ci_provider: github-actions
  ci_default_flow: manual-promotion
  bootstrapper_confidence: best-effort
  path_taken: custom
  quality_override: true
  self_check_answers:
    typed: true
    from_official_starter: false
    conventions: true
    docs_current: true
    can_judge_agent: true
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: false
  has_background_jobs: false
```

### Why this stack

Plain PHP 8.x with MySQL/MariaDB, server-rendered HTML, and minimal vanilla JavaScript is a deliberate fit for a single-user web app running on XAMPP locally and deploying to simple shared PHP hosting. No framework means zero dependency overhead and a deployment surface that works on any standard PHP host — a real advantage given the target environment. The trade-off is that folder layout, routing, and data-access conventions are not imposed: the agent will rely on explicit CLAUDE.md documentation of project structure instead of inferring them from a framework. PHP 8.x type hints, `declare(strict_types=1)`, and input validation at boundaries compensate for the absence of a Pydantic/Zod-style schema layer. Session-based auth is PHP-native — no external auth SDK required. Bootstrapper confidence is best-effort; scaffolding requires manual steps rather than a one-shot CLI command.

## Pre-scaffold verification

| Signal      | Value    | Severity | Notes                                                                 |
| ----------- | -------- | -------- | --------------------------------------------------------------------- |
| npm package | not run  | —        | not a JS starter; no npm package in cmd_template                      |
| GitHub repo | not run  | —        | docs_url is php.net, not a GitHub URL; no pushed_at signal available  |

No recency signal available for this starter. Proceeding.

## Scaffold log

**Resolved invocation (attempt 1)**: `mkdir -p public src config && composer init --name=app/app --description='Plain PHP 8.x web app' --no-interaction`
**Exit code (attempt 1)**: 127 — `composer: command not found`. Composer is not installed in the Git Bash PATH on this XAMPP Windows environment.

**Best-effort adaptation**: cmd_template updated in registry to replace `composer init` with a `printf` heredoc that writes `composer.json` directly (no external tool dependency). This is consistent with `bootstrapper_confidence: best-effort` and `quality_override: true`.

**Resolved invocation (attempt 2, adapted)**: `mkdir -p public src config && printf '{\n    "name": "app/app",\n    "description": "Plain PHP 8.x web app",\n    "require": {\n        "php": "^8.0"\n    }\n}\n' > composer.json`
**Strategy**: native-cwd (scaffold directly into the current directory)
**Exit code**: 0
**Pre-flight files-to-touch**: public/, src/, config/, composer.json
**Files written by CLI**: 1 (composer.json; public/, src/, config/ already existed in cwd)
**Pre-existing files preserved**: public/, src/, config/ (mkdir -p was a no-op; directories already present)

## Post-scaffold audit

**Tool**: skipped — no built-in audit tool for php
**Recommended external tool**: [Roave security-advisories](https://github.com/Roave/SecurityAdvisories) (Composer plugin that prevents installing packages with known CVEs) or [local-php-security-checker](https://github.com/fabpot/local-php-security-checker) (standalone CLI).

## Hints recorded but not acted on

| Hint                    | Value                                                                              |
| ----------------------- | ---------------------------------------------------------------------------------- |
| bootstrapper_confidence | best-effort — surfaced in conversation; no automated compensation in v1            |
| quality_override        | true — user proceeded past a quality gate in stack selection; no compensation in v1 |
| path_taken              | custom                                                                             |
| self_check_answers      | typed: true, from_official_starter: false, conventions: true, docs_current: true, can_judge_agent: true |
| team_size               | solo                                                                               |
| deployment_target       | shared-hosting                                                                     |
| ci_provider             | github-actions                                                                     |
| ci_default_flow         | manual-promotion                                                                   |
| has_auth                | true                                                                               |
| has_payments            | false                                                                              |
| has_realtime            | false                                                                              |
| has_ai                  | false                                                                              |
| has_background_jobs     | false                                                                              |

## Next steps

Next: a future skill will set up agent context (CLAUDE.md, AGENTS.md). For now, your project is scaffolded and verified — happy hacking.

Useful manual steps in the meantime:
- `git init` (if you have not already) to start your own repo history.
- Install [Composer](https://getcomposer.org/download/) for PHP dependency management (not available in this environment at run time).
- Once Composer is installed, run `composer install` to wire up autoloading from the generated `composer.json`.
- Review `composer.json` and add libraries as needed (e.g., a thin router, a PDO wrapper, a templating library).
- The `has_auth: true` flag is recorded but not scaffolded — session-based auth must be implemented manually.
- Address audit findings per your project's risk tolerance once `local-php-security-checker` or Roave security-advisories is configured.

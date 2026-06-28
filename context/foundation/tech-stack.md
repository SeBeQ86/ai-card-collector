---
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
---

## Why this stack

Plain PHP 8.x with MySQL/MariaDB, server-rendered HTML, and minimal vanilla JavaScript is a deliberate fit for a single-user web app running on XAMPP locally and deploying to simple shared PHP hosting. No framework means zero dependency overhead and a deployment surface that works on any standard PHP host — a real advantage given the target environment. The trade-off is that folder layout, routing, and data-access conventions are not imposed: the agent will rely on explicit CLAUDE.md documentation of project structure instead of inferring them from a framework. PHP 8.x type hints, `declare(strict_types=1)`, and input validation at boundaries compensate for the absence of a Pydantic/Zod-style schema layer. Session-based auth is PHP-native — no external auth SDK required. Bootstrapper confidence is best-effort; scaffolding requires manual steps rather than a one-shot CLI command.

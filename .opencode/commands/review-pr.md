---
description: Review all changed files in this PR against Serveo repo rules and flag any violations.
---

You are reviewing a pull request for the Serveo project.

Check all changed files against:
@AGENTS.md
@architecture.md
@business-rules.md
@definition-of-done.md
@role-permissions.md

For each changed file, verify:
- Follows the layered architecture (Controller → Service → Model)
- No business logic in controllers or views
- Permissions are checked via policy or middleware, not inline
- No raw SQL; use Eloquent or Query Builder
- Migrations are reversible and have a matching `down()` method
- New public methods have PHPDoc
- Every changed behaviour has a corresponding test

Return a checklist with PASS / FAIL / WARN per item and a short explanation for each failure or warning.

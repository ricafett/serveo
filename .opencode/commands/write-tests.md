---
description: Add or update PHPUnit tests for all behaviour touched in the current branch. Do not change production code.
---

You are writing tests for the Serveo project.

Read the test conventions in:
@AGENTS.md

For every method or behaviour changed in this branch, write or update a PHPUnit test that:
- Uses the existing test base classes and factories
- Covers the happy path
- Covers at least one failure or edge case
- Asserts HTTP status, response shape, and side-effects (events, DB state)
- Is isolated and does not depend on external services

Do not modify production code.
Run `php artisan test` as the final verification step.

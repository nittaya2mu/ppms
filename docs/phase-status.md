# Phase status

Design source: Phase 1 document "PPMS — Phase 1: Requirement Analysis & System Design" (Claude doc).
Section numbers below (DD-xx, FR-xx, OQ-xx, T01–T32) refer to that document.

## Business-rule decisions in effect

The 27 open questions (OQ-01 … OQ-27) were approved to proceed **with the proposed defaults**.
Each default is a row in `system_settings` (see `database/seed.sql`) so it can be changed without code changes.

| OQ | Default in effect | Setting |
| --- | --- | --- |
| 01 | Group size 2–3 including leader | `group.min_members`, `group.max_members` |
| 02 | Invited members must accept | `group.invite_requires_accept` |
| 05 | Late submissions accepted and flagged | `deadline.allow_late_default`, `deadlines.allow_late` |
| 08 | Conflicted admin blocked when another admin exists | `decision.block_conflicted_admin` |
| 09 | Rejected topic is terminal; group may start a new topic | schema `project_topics.current_key` |
| 10 | Admin revision goes back through the advisor | `topic.admin_revision_route` |
| 11 | Committee sees peer evaluation after both submit | `evaluation.peer_visible_after_both` |
| 12 | Release on final decision; committee names hidden | `evaluation.release_on_decision`, `evaluation.show_committee_names` |
| 14 | Approved with Minor Revisions requires a revised version | `decision.minor_requires_revision` |
| 15 | Justification when decision differs from at least one recommendation | `decision.justify_when` |
| 16 | Score-gap flag off | `evaluation.score_gap_flag` |
| 17 | Advisor "Not Approved" goes to Final Review | workflow `advisor_not_approve` |
| 19 | Unlimited revision rounds | `revision.max_rounds` |
| 20 | Proposal PDF ≤ 20 MB; response ≤ 10 MB | `upload.*` |

## Phase 3 — Database (done, tested)

- `database/schema.sql`: 32 tables (T01–T32), FKs, unique/check constraints, generated-column uniqueness for
  "one active group per student", "one leader", "one advisor", "one non-rejected topic".
- 23 triggers: append-only decision/log tables, committee validation (distinct, not the advisor, active committee
  role), assigned version fixed per round, submitted evaluations locked (reopen needs admin + reason),
  proposal/topic versions immutable.
- Tests: `tests/Integration/SchemaConstraintsTest.php` (16 tests).

## Phase 4 — Authentication and role management (done, tested)

- Login by username or email, lockout after 5 failures, inactive/soft-deleted accounts refused.
- Forced password change on first login, admin password reset with temporary password.
- Multi-role users with active-role switching; role-based menus and route middleware.
- User management: list/search/filter/paginate, create (with student profile), edit, roles, deactivate.
- Dashboards for all four roles reading live counts from the database.
- In-app notification list.
- Tests: `tests/Integration/AuthServiceTest.php`, HTTP smoke test (login, CSRF 419, forced password change,
  student blocked from `/admin/*` with 403 even after attempting to switch role).

## Built ahead for later phases (unit-tested, not yet wired to screens)

- `Services/Workflow/Workflows.php`: group, topic (T1–T10) and proposal (P1–P16) state machines.
- `Services/Workflow/WorkflowEngine.php`: locked, transactional transitions with status history and audit.
- `Services/ScoreCalculator.php`: Section 9.2/9.5 formulas, weight validation, disagreement and justification rules.

## Known limitations

- Menu items for Phases 5–12 are shown as "อยู่ระหว่างพัฒนา" (disabled).
- Excel/PDF export libraries (PhpSpreadsheet, mPDF) are not installed yet (Phase 12).
- Email notifications are designed (`notifications.email_status`) but not sent (optional extension).
- Course code and semester dates in `seed.sql` are placeholders marked `[CONFIRM]`.
- PHPUnit could not be installed in the build environment; tests use the bundled runner `tests/run.php`.

## Next: Phase 5 — Group registration and topic submission

Academic year / offering / deadline admin screens, group creation and invitations, group verification,
advisor request and assignment, topic draft and submission with supporting documents.

## Deployment target: InfinityFree (chosen 2026-11-24)

- InfinityFree grants no TRIGGER privilege and foreign keys are unreliable, so the host uses
  `database/schema_shared_hosting.sql`, generated from `schema.sql` by `tools/build-shared-schema.php`
  (a unit test fails if the generated file is stale).
- The trigger rules are duplicated in PHP: `App\Core\IntegrityGuard` (every query: append-only tables,
  no deletion of proposal versions) and `App\Services\Rules\*` (committee validity, evaluation lock and
  reopen, immutable versions). Future services for Phases 5-11 must call these rules.
- Tests run in both modes: `php tests/run.php` and `PPMS_SCHEMA=shared php tests/run.php`.
- Verified: import as a MySQL user limited to InfinityFree's privileges (ALTER, CREATE, DELETE, DROP,
  INDEX, INSERT, SELECT, UPDATE, LOCK TABLES), and the packaged zip served from an Apache htdocs root
  with production settings (login, password change, user management, private folders return 403).
- Not verified on InfinityFree itself (no account in the build environment).
- Guide: `docs/deploy-infinityfree.md`. Package: `php tools/package.php`.

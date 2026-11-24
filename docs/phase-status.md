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

## Phases 5–12 — Workflow modules (done, tested)

| Phase | Module | Screens / routes |
| --- | --- | --- |
| 5 | Project groups: create, invite/accept, submit, admin verify/reject, advisor request/assign/change, admin member changes with history | `/groups`, `/groups/{id}` |
| 6 | Topic submission: 11 fields, drafts, versions (v1, v2…), supporting documents, late flag | `/projects/{id}#topic`, `/topics` |
| 7 | Topic approval: advisor (approve / revise / reject) → Course Administrator final approval | `/topic-approval` |
| 8 | Proposal submission: PDF upload, 13-section checklist, response-to-comments for revisions, immutable versions | `/projects/{id}#proposal`, `/proposals` |
| 9 | Advisor proposal review + committee assignment (2 distinct members, advisor excluded, deadline, replace before submit), revision Mode A/B | `/advisor-review`, `/committee-assignment` |
| 10 | Independent committee evaluation: 1–5 rubric, live weighted score, recommendation, 5 comment types, draft/submit lock, admin reopen with reason | `/evaluations`, `/evaluations/{id}` |
| 11 | Final approval: S1, S2, S_avg, disagreement flag, no auto-approval, justification rule, conflict-of-interest block, printable approval summary | `/final-approval`, `/projects/{id}/summary` |
| 12 | 12 reports with on-screen filter, Excel (.xlsx), CSV and print-to-PDF; system settings (OQ defaults, academic years/semesters/courses/offerings, deadlines, rubric with weight check and lock) | `/reports`, `/admin/settings/*` |

Access control: `AccessPolicy` checks group membership / advisor / committee / admin on every project page and
file download; students never see committee names (`evaluation.show_committee_names`) or unreleased scores.

Tests:
- `tests/Integration/AcceptanceScenarioTest.php` — Section 22 scenario through the service layer plus negative cases.
- `tests/http_e2e.php` — the same scenario through real HTTP (login, CSRF, forms, uploads) for all roles, then
  opens every page for every role and every report in all formats (154 checks).
  Run against a fresh database loaded with schema + seed + seed_demo:
  `PPMS_DB_DATABASE=ppms_e2e php -S 127.0.0.1:8000 -t public public/index.php` then
  `PPMS_DB_DATABASE=ppms_e2e php tests/http_e2e.php`.

## Known limitations

- PDF export uses the browser's print-to-PDF on a print layout (no server-side PDF library on shared hosting).
- Email notifications are designed (`notifications.email_status`) but not sent; in-app notifications work.
- Course code and semester dates in `seed.sql` are placeholders marked `[CONFIRM]` (editable in System Settings).
- PHPUnit could not be installed in the build environment; tests use the bundled runner `tests/run.php`.

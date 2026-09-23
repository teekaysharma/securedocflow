# CLAUDE.md — OpenDocMan (this fork)

This file is read automatically by Claude Code at the start of every session in this repo. Treat
it as authoritative project context. Written 2026-09-23, adopting the AI-native SDLC playbook
(https://claude.com/blog/the-ai-native-sdlc-playbook) per the repo owner's explicit instruction —
see `docs/superpowers/intents/2026-09-23-adopt-ai-native-sdlc-intent.md` for the full ask, and
`docs/superpowers/INDEX.md` for where every feature/initiative currently stands.

**Scope note (avoid confusing this with other files also named CLAUDE.md or tracking this same
project):** there is a global `~/.claude/CLAUDE.md` that applies to every Claude Code session on
this machine — unrelated to this repo, no authority here. There is also a **separate, non-public
tracking system for the real deployment this fork was extracted from** — `PROJECT_PLAN.md`,
`MEMORY_ARCHIVE_*.md`, and `SECURITY_AUDIT_2026-08-11.md` in
`C:\Users\LENOVO\Documents\ClaudeCowork\OUTPUTS\OpenDocMan\`, entirely outside this git repo. That
split is deliberate, not an oversight — see Ownership below. This file never names the client, the
real users, or real document/deployment specifics; that content stays in the non-public tracking
system, and this file only ever points across to it in the abstract.

## Ownership

This git repo is a **sanitized public GitHub portfolio fork**, extracted 2026-08-11 from a real
client engagement. It contains no client name, no real documents, no real user data — verified by
a full repo-tree search before the first commit (see `SECURITY.md`'s hardening summary and the
README's "About this fork" section). Do not reintroduce client-identifying content here under any
circumstance, including in code comments, test fixtures, `docs/superpowers/` entries, or commit
messages — if a future task's context includes real client specifics, generalize them before they
land in this repo, or route that work to the non-public tracking system instead.

## Repo

- No remote configured yet — local-only as of this writing, one commit (`f0fa288`, 987 files).
  Pushing to GitHub needs the repo owner's own credentials; not something Claude does
  unprompted here.
- No CI/CD (`.github/workflows/release-please.yml` handles releases only, not tests/lint on push).
- No PR workflow observed yet — only one commit exists, so there's no established merge practice
  to describe accurately. Don't assume a policy that hasn't actually been demonstrated; ask, or
  default to direct commits with the same per-instance explicit-approval discipline any repo needs
  for its first several changes.
- Dependencies: Composer, `vendor-dir` set to `application/vendor` (not the repo root's `vendor/`).
  **Both prod and dev dependencies are committed** (not gitignored) — a pre-existing project
  convention (the `Dockerfile`/`Makefile` don't run `composer install` anywhere), extended
  2026-09-23 to also commit dev deps (phpunit, mockery, phplint) so the test suite is runnable
  immediately after clone with no setup step. `application/vendor` is ~13MB as of this writing —
  reasonable, but don't casually add heavy dev tooling without checking that stays true.

## Development process — AI-native SDLC playbook (adopted 2026-09-23)

Every feature's artifact chain, in order:

1. **`docs/superpowers/intents/YYYY-MM-DD-<topic>-intent.md`** — the raw ask, in the repo owner's
   own words, timestamped. Written *before* the spec. Captures Stage 1 (Plan).
2. **`docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`** — via `superpowers:brainstorming`.
   Questions asked one at a time, 2-3 approaches proposed with a recommendation, design presented
   in sections with approval after each, written up, self-reviewed, then the owner reviews the
   actual file before anything proceeds.
3. **`docs/superpowers/plans/YYYY-MM-DD-<topic>.md`** — via `superpowers:writing-plans`.
   Task-by-task, verbatim code, no placeholders.
4. **Execution** — via `superpowers:subagent-driven-development` when time allows: fresh
   implementer + task reviewer per task, a final whole-branch review, fix wave, scoped re-review
   before merge.
5. **`superpowers:finishing-a-development-branch`** — merge/PR/keep decision.

Plan-mandated findings (a defect traceable to the plan's own text, not implementer deviation)
always go to the repo owner via `AskUserQuestion` — never silently fixed or dismissed, even when
the "right" answer looks obvious.

**All feature work from 2026-08-04 through 2026-08-11 predates this process** — built through
direct plan-mode sessions and ad hoc fixes, not the intent→spec→plan chain. That work is real and
verified (see `docs/superpowers/INDEX.md`'s backfilled rows); it just wasn't produced this way.
Don't retroactively force it into the new structure — apply the playbook going forward.

**Known gaps against the playbook's later stages** (as of 2026-09-23, not yet closed):
- Test suite exists and mostly runs (see Commands below) but has real, disclosed pre-existing
  failures — not zero coverage, but not a clean baseline either.
- No `.claude/skills/` yet for this repo's own policy areas (SQL/table-prefix conventions, CSRF
  patterns, file-path validation) — candidate for a future initiative, see INDEX.md.
- No hooks (`.claude/settings.json`) — no build-time guardrails exist.
- No CI/CD, no PR-gated review, no `REVIEW.md`.
- No monitoring, no Stage 6 maintenance loop — this is a portfolio artifact, not a monitored
  production service.

## Commands

- **Install:** `php composer.phar install` (both are committed already; only needed after a
  version bump or on a machine without the current `application/vendor/` state).
- **Test:** `php application/vendor/phpunit/phpunit/phpunit --colors=never` (the `vendor/bin/`
  shell wrapper doesn't run directly under Windows PHP — call the real entry point instead).
  Or via Composer: `composer test` (same runner, with the project's own noise filter for a known
  benign diagnostic line — see "Things Claude gets wrong" below).
  **Current verified baseline (2026-09-23): 297 tests, 31 errors, 0 failures.** Not clean — see
  the next paragraph — but a genuine, checked number, not an assumption.
- **Lint:** `php application/vendor/overtrue/phplint/bin/phplint --no-configuration application/`
  (same real-entry-point caveat as phpunit). Verified clean: `OK! (Files: 78, Success: 78)` against
  `application/models` + `application/controllers` as of 2026-09-23.

**Test suite health, disclosed rather than hidden:** the 31 errors are concentrated in
`DeptPermsTest` (3), `FileDataTest` (12), `UserMethodsTest` (4), `UserModelTest` (7), and
`UserPermissionOrchestratorTest` (5) — confirmed via `--filter FileDataTest` run in complete
isolation that these are genuinely pre-existing, independent of each other (not one cascading
failure). Root causes identified so far: stale PDO-mock row shapes that don't include fields added
since the tests were written (e.g. `doc_version`), and — for `UserPermissionOrchestratorTest`
specifically — `UserPermission`'s constructor now builds a `Group_Perms` object (added with the
Groups feature) that older mocks don't account for. **Four real, verified fixes landed 2026-09-23**
while establishing this baseline (see `docs/superpowers/INDEX.md`): a genuine class-autoload gap
(`Group_Perms.class.php` missing from a test file's manual require list), a CSRF-detection test
false positive (a form using an action-scoped token variable name the regex didn't recognize), and
two pairs of tests asserting pre-security-migration behavior (unsalted MD5/legacy-`PASSWORD()`
password checks, and a `null`-vs-`[]` default) that the 2026-08-11 security hardening correctly
changed out from under them. **The remaining 31 are a real, open gap, not yet fixed** — don't
claim a clean test suite until they're actually resolved.

## Stack and architecture rules

- PHP 8.2, MySQL 8+/MariaDB 10+, Apache with `mod_rewrite`. Legacy per-file controller/model
  structure (no framework router in active use — `public/index.php` maps request paths directly
  to `application/controllers/<name>.php`).
- CSRF: Paragonie AntiCSRF via a `CsrfProtection` singleton
  (`application/includes/csrf/CsrfProtection.php`). `getTokenField($action)` /
  `validateToken($data, $action)` — but `validateToken()`'s `$data` parameter is a no-op; the
  underlying library always reads real `$_POST` regardless of what's passed. A GET-triggered
  mutating action can't use this directly — see "Things Claude gets wrong" below for the pattern
  that works.
- Every SQL query must include the table prefix explicitly:
  `{$GLOBALS['CONFIG']['db_prefix']}$this->tablename` (or the literal `odm_` prefix) — see "Things
  Claude gets wrong," this is a real, repeated source of bugs in this codebase.
- File-serving code (anything building a filesystem path from a request parameter) must validate
  and cast to a clean type (usually `(int)`) *before* it's used in the path — never trust a
  request-derived string in a path just because a permission check elsewhere used a cast version
  of the same value. A real IDOR (crafted `id=5/../6` served a different document's bytes while
  passing permission checks against an authorized one) came from exactly this gap; fixed, but the
  pattern is worth remembering when adding any new file-serving code.

## Standing behavioral rules

Distilled from this project's own history — every one of these traces to a real bug or incident
found during the 2026-08 build/audit, not a generic checklist:

**Investigate before claiming.** Never state what a file, query, or config says without opening it
this session. This repo's own history includes: a table-prefix bug that only appeared once dead
code was actually wired up and called for the first time; a MySQL-8-removed function
(`PASSWORD()`) that would have kept silently corrupting error responses if the fix had trusted the
old code's apparent correctness instead of testing a real wrong-password login; and — from the
2026-09-23 test-suite audit — an assumption that fixing one class-autoload gap would fix "the"
test failures, when in fact most of the remaining failures were unrelated and already there,
just not visible in a truncated tail of the first test run. Verify empirically; don't extrapolate
from a partial view.

**Root-cause every test/bug before fixing it, every time — including bugs found mid-investigation,
not just ones reported to you.** The 2026-09-23 test-suite pass is the concrete example on file:
tracing "Class Group_Perms not found" all the way to a stale manual require-list (not a missing
file), and confirming a suspected regression by isolating variables (stash, revert one file,
`--filter` a single test class) rather than assuming causation from a plausible-looking
correlation.

**Surgical fixes, disclosed gaps.** Fix the specific, verified root cause. When a fix reveals a
larger pattern (like the 31 remaining test failures), stop, document what's known, and say so
explicitly rather than either chasing every thread to completion unprompted or quietly leaving the
gap undocumented. `docs/superpowers/INDEX.md` and this file's Commands section are where that
disclosure lives — keep them current.

**No client content in this repo, ever** — see Ownership above. This is the one rule in this file
with zero exceptions.

## Session start checklist

1. `git status`, `git log --oneline -10` — confirm what's actually committed vs. working-tree-only.
2. Read `docs/superpowers/INDEX.md` — one row per feature/initiative, current stage, links to the
   relevant intent/spec/plan files. Answers "where does everything stand" without re-deriving it.
3. `php application/vendor/phpunit/phpunit/phpunit --colors=never` and the phplint command above —
   report actual output, compare against the baseline in this file's Commands section, don't
   assume either is still accurate.
4. Report current verified state before starting new work.

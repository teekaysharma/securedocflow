# Project Index

**What this file is:** the one place to look to know where this fork stands, without reading
every spec/plan file or the codebase itself. One row per feature or process initiative. Detail
lives in the linked `intent.md`/`spec.md`/`plan.md` files where they exist — this table is
pointers and current status only.

**Client-anonymity rule:** this file is part of the public repo (see `CLAUDE.md`'s Ownership
section) — no client name, no real deployment specifics, no real document/user counts. Describe
features generically, the way `README.md` already does.

**Maintenance rule:** update this file in the same commit as any intent/spec/plan file being
created, or any row's status changing — never a separate remembered chore.

**Stage vocabulary:** Intent captured → Spec approved → Plan written → Building → Shipped, with
Deferred / On hold / Not started / Status unknown for work not moving through that pipeline
right now.

## Next priorities

Not yet sequenced by the repo owner as an explicit ordered list (unlike a single-owner project
with one active track, this fork's real priorities live in the private, non-public tracking
system — see `CLAUDE.md`'s Ownership section). What's visibly open from this repo's own state
alone, unordered:

- Push to a real GitHub remote (needs the owner's own credentials).
- Resolve the 31 disclosed pre-existing test failures (`CLAUDE.md`'s Commands section has the
  current breakdown and root-cause leads).
- `.claude/skills/` for this repo's own recurring conventions (table-prefix SQL pattern, CSRF
  token patterns, file-path validation) — would close part of the playbook's Stage 2 (Design)
  dependency the same way `ghgcalculator`'s equivalent skills closed it there.
- CI/CD — currently none at all, not even lint/test on push.

## Product features

| Feature | Intent | Spec | Plan | Stage | Status |
|---|---|---|---|---|---|
| Classification-driven access control (5-tier: Public/Internal/Limited/Sensitive/Highly sensitive) | — | — | — | Shipped | Enforced via a checkout lock on Sensitive/Highly-sensitive documents and a classification-driven minimum Staged Approval floor at upload — not metadata-only. Predates this repo's playbook adoption; no intent/spec/plan recorded. |
| Staged Approval workflow | — | — | — | Shipped | Named, reusable multi-stage templates with per-stage designated approvers, distinct from the classic single-reviewer path. |
| Department Head assignment | — | — | — | Shipped | Elevated per-department authority (classification-setting, validity-date-setting, Access Request resolution). |
| Groups | — | — | — | Shipped | Reusable named permission groups layered on top of the existing individual/department permission model. |
| Document serial numbers | — | — | — | Shipped | Auto-generated per department, carried through into every filename the document is served under (view, download, and converted-preview paths alike). |
| Access Request workflow | — | — | — | Shipped, extended | A user lacking sufficient access can request a specific rights level; a Department Head or Admin resolves it. Extended later: the resolution screen now surfaces the document's *current* classification and visibly flags it if changed since the request was submitted — closes a real gap found by direct stress-testing (a resolver could previously approve a stale request with zero awareness the document's sensitivity had changed). Also closed a real TOCTOU race in the resolution logic (two concurrent resolutions could both pass a stale pending-check) — made atomic, verified via an isolated unit test. |
| In-browser document preview | — | — | — | Shipped | Word/Excel/PowerPoint/OpenDocument via headless LibreOffice conversion, cached per document version. Converted PDF metadata (author, source application, etc.) is stripped before being served — a preview shouldn't leak more about a Sensitive document than its classification intends. Includes a download fallback when conversion fails and serial-number-prefixed output filenames. |
| Security hardening pass | — | — | — | Shipped | Full audit against controllers, models, the CSRF layer, and the installer. 2 Critical, 4 High, 2 Medium, 3 Low/Informational findings, all fixed and verified against the running application (not just read and assumed correct) — including an unauthenticated installer path that could reinitialize the entire database, and a confirmed IDOR in the file-serving path. Full findings table in `SECURITY.md`. |
| GitHub publication prep | — | — | — | Shipped | Full-tree secret/real-data search (none found beyond the already-excluded local config file), `.gitignore` hardened, README rewritten to lead with this fork's distinct work, security summary added to `SECURITY.md`, initial commit made. No remote added — that's the repo owner's own action. |

## Platform/process initiatives

| Initiative | Stage | Status |
|---|---|---|
| `intent.md` → `spec.md` → `plan.md` artifact chain | Adopted | Established 2026-09-23 per https://claude.com/blog/the-ai-native-sdlc-playbook, on the repo owner's explicit instruction — see [intent](intents/2026-09-23-adopt-ai-native-sdlc-intent.md). Applied going forward; not backfilled onto the rows above (no value reconstructing intent that was never recorded — this index's rows are the equivalent lightweight pointer instead, matching the convention used on the repo owner's other playbook-adopting projects). |
| `CLAUDE.md` as shared institutional knowledge | Done | Created 2026-09-23, modeled on the repo owner's other two playbook-adopting projects (same house style: Ownership, Repo, Development process, Commands with real verified output, Standing behavioral rules, Session start checklist). |
| This index | Done | Created 2026-09-23. |
| Test suite health audit | Shipped (partial) | 2026-09-23: ran the existing PHPUnit suite for the first time as part of establishing this file's "healthy output" baseline — found it wasn't healthy (32 errors, 4 failures) and fixed four real, verified root causes: a class-autoload gap (`Group_Perms.class.php` missing from a test file's manual require list — the class exists, just wasn't loaded), a CSRF-detection test false positive (regex only recognized one literal Smarty variable name, not the action-scoped one a real form correctly uses), and two pairs of tests asserting behavior the 2026-08 security migration intentionally changed (MD5/legacy-`PASSWORD()` password checks, a `null`-vs-`[]` default). Verified each fix in isolation via `git stash`/single-file revert/`--filter`, not assumed. **31 errors remain, disclosed not hidden** — concentrated in `DeptPermsTest`, `FileDataTest`, `UserMethodsTest`, `UserModelTest`, `UserPermissionOrchestratorTest`; confirmed independent of each other and of the fixes above (e.g. `FileDataTest` fails identically in complete isolation). Root cause for most: stale PDO-mock row shapes missing fields added since the tests were written. Not fixed this round — a large enough body of work (auditing and updating mocks across five test files) to warrant its own scoped pass rather than folding into this one. See `CLAUDE.md`'s Commands section for the exact current numbers. |

# Intent: Would Laravel benefit this project?

**Originator:** teekaysharma@googlemail.com (product owner)
**Captured:** 2026-10-07
**Status:** Captured, not yet brainstormed. Next step is `superpowers:brainstorming` in a fresh session (architectural path).

## Origin, in the originator's own words

> "this project is running on php, i would like to understand if laravel will add any benefits to
> it or not"

and, after the first answer:

> "For doing this, It would ideally be a fresh session that takes the summary from this page and
> whatevever has been built so far. correct me if I am wrong but I understand we are talking about
> a completely different level that is effectively an upgrade of this existing project."

## Context at time of capture

The first answer (given in the session that discovered the problems below) was: yes, Laravel
would remove real weaknesses, but it is a rewrite, not a refactor. The architecture is different
enough (per-file procedural controllers, Smarty templates and raw PDO with a manual table prefix,
versus routed MVC, Blade and Eloquent) that every custom feature would be re-implemented rather
than ported. The recommendation was not to do it against the system that is live for a real client,
and to revisit it if the multi-tenant productisation track becomes real.

The originator's reading, that this is a different level and ideally a fresh session, is correct
with one adjustment: it is a new codebase (a v2) that reuses this project's rules and data model, not
an in-place upgrade. That is why the handoff should be repo files and not a pasted chat summary.

What the weaknesses were, found by actually running the install on 2026-09-23 (see
`docs/superpowers/INDEX.md`, "Fresh-install Docker verification and schema completeness"):

- The schema for the project's own features existed in no `CREATE TABLE` and no migration. It had
  to be reverse-engineered from application code. Reproducible migrations are the single thing a
  framework gives for free that this codebase lacked.
- A table-prefix convention enforced by hand in every query, a repeated source of bugs.
- Hand-rolled CSRF wrapper and a permission model spread over `User_Perms`, `Dept_Perms`,
  `Group_Perms` and `UserPermission`.
- 297 tests that mock PDO heavily and went stale against feature work.

## What a fresh session should start from

- `application/installer/SchemaBuilder.php`: now the complete, verified schema. It becomes the
  source for the Laravel migrations.
- `USER_GUIDE.md`, `README.md`, this index: the behaviour to preserve, from the user's side.
- The test suite as a list of behaviours to port, not code to reuse.
- `CLAUDE.md`: the permission model, the Access Request visibility rule, and the schema-has-two-
  homes rule.

## Open questions for brainstorming

1. New repository or a branch of this one? The live deployment must stay untouched either way.
2. What is the trigger: a second client (multi-tenant track), portfolio value, or maintainability?
   The answer changes how much of the rewrite is worth doing.
3. Big-bang rebuild or strangler (Laravel in front, old controllers behind it, moved one feature at
   a time)? The strangler path keeps the live system safe but is slower.
4. Does the data model change (single tenant versus tenant-aware from day one)?

## Disposition

Not started. Architectural path: questions one at a time, two or three approaches with a
recommendation, a sectioned design the originator approves, a written spec in
`docs/superpowers/specs/`, then `superpowers:writing-plans`. No code before the spec is approved.

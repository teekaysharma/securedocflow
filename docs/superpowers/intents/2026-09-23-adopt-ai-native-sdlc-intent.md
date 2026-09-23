# Intent: Adopt the AI-Native SDLC Playbook for This Project

**Originator:** teekaysharma@googlemail.com (product owner)
**Captured:** 2026-09-23
**Status:** Accepted — proceeded directly to `CLAUDE.md` + `docs/superpowers/INDEX.md` (see Disposition)

## Origin, in the originator's own words

> "This project was running on everything I thought of on the spur of the moment. What it did not
> follow was the anthropic Claude Code SDLC methodology. I want you, going forward, to follow the
> SDLC as outlined in the manual. https://claude.com/blog/the-ai-native-sdlc-playbook with all you
> have at your disposal, build up the cloud.md. Take as an example what has already been developed
> in other projects (the cloud.md). Build up the intent.md. You have your superpowers, which do a
> pretty good job, so build the claude.md based on the superpowers, the intent, and the specs
> accordingly."

("cloud.md" above is the originator's own spelling of `CLAUDE.md`, kept verbatim in the quote.)

## Context at time of capture

Prior to this request, the project ran through a single long continuous session (2026-08-04
through 2026-08-11) covering the full feature build (ICMH classification, Staged Approval, Groups,
Access Request, LibreOffice preview, serial numbers), a full security audit, and GitHub
publication prep — all done ad hoc, session-by-session, without the `intent.md → spec.md → plan.md`
artifact chain the playbook describes. That work is real and already verified (see
`docs/superpowers/INDEX.md`'s backfilled rows), it just wasn't produced through this process.

The originator has already adopted this same playbook on two other personal projects
(`ghgcalculator`, `GHG Documents Toolkit`) — both have a working `CLAUDE.md` +
`docs/superpowers/{intents,specs,plans}/` + `INDEX.md` set, used here as the direct template for
house style, section structure, and conventions, per the originator's explicit instruction to
"take as an example what has already been developed in other projects."

**A boundary specific to this project, absent from the other two:** this git repo
(`www/opendocman/`) is a sanitized public GitHub portfolio fork — no client name, no real
document/user data (see `SECURITY.md`'s hardening summary and the README's "About this fork"
section from the 2026-08-11 GitHub-prep work). The actual SustainaCert engagement history (real
deployment status, real document/user counts, the full security-audit narrative) is tracked
separately, outside this repo, in `C:\Users\LENOVO\Documents\ClaudeCowork\OUTPUTS\OpenDocMan\`
(`PROJECT_PLAN.md`, `MEMORY_ARCHIVE_*.md`, `SECURITY_AUDIT_2026-08-11.md`) — deliberately, so client
content never lands in the public repo. Confirmed with the originator before drafting anything:
keep the two separate, `CLAUDE.md` points across to the external tracking without pulling its
content in or naming the client.

## Disposition

Accepted. Given direct, largely complete instructions already in hand (read the playbook, model on
two existing same-owner projects, use superpowers) and an explicit answer on the one open design
question (the client-boundary question above), this proceeded straight to building the artifacts
rather than a full `brainstorming`-skill question round — consistent with how the ghgcalculator
project itself recorded its own playbook adoption (`CLAUDE.md` created directly, listed as "Done"
in its `INDEX.md`, no intent/spec of its own). This intent file exists because the originator asked
for `intent.md` explicitly as a named deliverable, and because it's the natural place to record the
client-boundary decision above for future sessions.

Produced:
- `CLAUDE.md` (repo root) — modeled on `ghgcalculator`'s and `GHG Documents Toolkit`'s, adapted to
  this project's stack (PHP/MySQL/Apache, not Node/Postgres) and its public-fork/private-tracking
  split.
- `docs/superpowers/INDEX.md` — backfills the 2026-08-04–2026-08-11 work as index rows (no
  intent/spec/plan links, same convention `ghgcalculator`'s `INDEX.md` used for its own
  pre-playbook work: "no value in reconstructing intent that was never recorded"), scrubbed of
  client-identifying specifics to match this repo's public-fork constraint.

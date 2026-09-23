# Contributing

SecureDocFlow is a personal portfolio project — a fork of [OpenDocMan](https://github.com/opendocman/opendocman)
extended with its own access-control, workflow, and security-hardening work (see the
[README](README.md) for what's original here). It isn't an actively-maintained open-source product
looking for a contributor community, so there's no CLA, no branch policy, and no SLA on review
turnaround.

That said, if you spot a real bug or have a fix, it's welcome:

1. Fork **this repository** (`teekaysharma/securedocflow`) — not upstream OpenDocMan, unless your
   fix belongs there instead (see below).
2. Open an issue first for anything non-trivial, so the change makes sense before you write it.
3. Make focused commits — one logical change per commit, clear message.
4. Run the test suite (`php application/vendor/phpunit/phpunit/phpunit --colors=never`, or
   `make test`) before opening a pull request against `master`.
5. Open the pull request against **this repository**.

If you've found a bug in behavior this fork inherited unchanged from upstream (not something built
on top of it), it likely belongs in [opendocman/opendocman](https://github.com/opendocman/opendocman)
instead — check the [README's "Credit where it's due" section](README.md#credit-where-its-due) for
what's actually original to this fork versus inherited.

For security issues, see [`SECURITY.md`](SECURITY.md) instead of opening a public issue.

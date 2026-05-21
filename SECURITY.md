# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |
| < 1.0   | No        |

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Email **security@deckapp.cloud** (or open a private [GitHub security advisory](https://github.com/getdeckapp/deck/security/advisories/new) on this repository) with:

- A description of the issue and impact
- Steps to reproduce
- Affected versions

We aim to acknowledge reports within a few business days and will coordinate a fix and disclosure timeline with you.

## Scope notes

Deck dashboards expose queue operations (cancel, block, retry, clear pending). Always restrict `/deck` with `deck.auth` or Horizon’s gate in production. Treat `DECK_API_KEY` like any production secret.

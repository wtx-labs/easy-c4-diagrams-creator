# Contributing to EasyC4 Diagram Creator

Thanks for your interest in contributing! This document covers how to set up a local environment, how to report bugs, and how to submit changes.

## Table of contents

- [Local setup](#local-setup)
- [Project structure](#project-structure)
- [Reporting bugs](#reporting-bugs)
- [Suggesting features](#suggesting-features)
- [Submitting a pull request](#submitting-a-pull-request)
- [Coding conventions](#coding-conventions)

## Local setup

**Requirements:** PHP 8.0 or higher (8.1+ recommended).

```bash
git clone https://github.com/wtx-labs/easy-c4-diagrams-creator.git
cd easy-c4-diagrams-creator
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000` in your browser. No build step, no dependencies to install.

To verify the API is up:

```bash
curl http://127.0.0.1:8000/api/health
```

## Project structure

```
public/          # webroot — served directly (HTML, JS, assets, API router)
  index.html     # single-page UI
  index.php      # API router (entry point for /api/*)
  examples/      # sample .puml and .mmd files loaded by the UI
  docs/          # OpenAPI spec
backend/
  src/           # PHP conversion logic (parsers, emitter, validator)
  data/          # EasyC4 shape library JSON
docs/            # deployment and technical notes
```

`backend/` lives outside the webroot intentionally — it is never served directly.

## Reporting bugs

Please [open an issue](https://github.com/wtx-labs/easy-c4-diagrams-creator/issues) and include:

- The C4 source text that caused the problem (PlantUML or Mermaid).
- What you expected to happen vs. what actually happened.
- PHP version if the issue is server-side (`php --version`).

## Suggesting features

[Open an issue](https://github.com/wtx-labs/easy-c4-diagrams-creator/issues) with a short description of the use case. We keep the scope intentionally narrow (C4 model, draw.io output), so it helps to explain *why* something fits that goal.

## Submitting a pull request

1. Fork the repository and create a branch from `main`.
2. Make your changes — keep commits focused and the diff readable.
3. Test manually: paste a PlantUML C4 and a Mermaid C4 diagram, download the `.drawio`, open it in diagrams.net and verify the result looks correct.
4. Open a pull request with a clear description of what changed and why.

Small, focused PRs are much easier to review than large ones.

## Commit message convention

This project follows [Conventional Commits](https://www.conventionalcommits.org/).

Format: `type(scope): short description`

| Type | When to use |
|------|-------------|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `refactor` | Code change that is neither a fix nor a feature |
| `test` | Adding or updating tests |
| `chore` | Tooling, config, dependencies |
| `perf` | Performance improvement |

Examples:

```
feat(parser): support C4 deployment diagrams in PlantUML
fix(mermaid): handle missing closing brace in C4Container
docs: add CONTRIBUTING guide
chore: update EasyC4 shape library data
```

Rules:
- Use the imperative mood in the description: "add", not "added" or "adds".
- Keep the first line under 72 characters.
- Add a blank line before the body if more context is needed.

## Coding conventions

**PHP (backend):**
- `declare(strict_types=1)` at the top of every file.
- Final classes, no inheritance.
- Static methods where state is not needed.
- No external dependencies — pure PHP only.

**JavaScript / HTML (frontend):**
- Vanilla JS, no frameworks, no build tools.
- Keep the UI self-contained in `public/index.html`.

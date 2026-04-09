## EasyC4 Diagram Creator - Technical details

This document collects technical notes that are intentionally kept short in the main `README.md` - architecture summary, deployment pointers, project structure, and HTTP API.

### Architecture overview

- Accepts **PlantUML C4** or **Mermaid C4** as text input.
- Validates and converts it into a **`.drawio`** XML diagram compatible with diagrams.net / draw.io.

### Deploy (PHP hosting)

- Your **document root / webroot** should point to `public/`.
- If your hosting forces `public_html/`, upload the **contents of `public/`** into `public_html/`, and place `backend/` next to it (one level above).

See `docs/DEPLOY.md` for step-by-step deployment notes.

### Project structure

- `public/` - webroot: UI (`index.html`) + assets + API router (`index.php`)
- `public/docs/` - OpenAPI spec `easy-c4-api-specification.yaml` (served as `/docs/easy-c4-api-specification.yaml`; linked from the app footer)
- `backend/` - conversion/validation logic and data (outside webroot)
- `docs/` - extra docs (deploy/how-to/notes)

### API reference (OpenAPI)

- `public/docs/easy-c4-api-specification.yaml` - OpenAPI 3 spec (paths, methods, request/response bodies, status codes); the only copy maintained in this repo.
- `GET /api/health` - liveness check; JSON such as `{ "ok": true, "shapes": … }`.
- `POST /api/validate` - validate PlantUML or Mermaid C4 in the body; JSON result with errors/warnings. Used by the browser UI before convert.
- `POST /api/convert` - convert C4 text in the body to a `.drawio` XML response (`application/xml` on success, JSON error on failure). Used by the browser UI for download and “open in diagrams.net”.

### Public application instance API usage examples

The hosted app **[https://c4.wtx.pl](https://c4.wtx.pl)** exposes the same HTTP API as a self-hosted deployment. Typical uses:

- **CI / scripts**: after a `.puml` or `.mmd` file changes in a repo, call **`POST /api/convert`** and save the response as a `.drawio` artifact.
- **Internal tools**: a small backend or low-code flow can POST diagram text and attach the returned XML to a wiki or ticket.
- **Local parity**: run `php -S 127.0.0.1:8000 -t public` and call `http://127.0.0.1:8000/api/...` with the same paths.

**Primary integration endpoint** (what most automations need):

- **`POST /api/convert`** — body = raw C4 text (`Content-Type: text/plain; charset=utf-8`) **or** JSON `{ "puml": "..." }` / `{ "source": "..." }`.  
  Success: **`200`** with **`application/xml`** (draw.io file); typical failure: **`400`** with JSON `{ "error": "..." }`.

Example against the public site (run from a directory that contains your source file, or use an absolute path after `@`):

```bash
curl -sS -X POST "https://c4.wtx.pl/api/convert" \
  -H "Content-Type: text/plain; charset=utf-8" \
  --data-binary "@path/to/diagram.puml" \
  -o diagram.drawio
```

If your shell’s current directory is **`public/examples`** in this repo, use the sample file name directly (same files the UI loads as “PlantUML C4 examples”):

```bash
curl -sS -X POST "https://c4.wtx.pl/api/convert" \
  -H "Content-Type: text/plain; charset=utf-8" \
  --data-binary "@c1-context.puml" \
  -o diagram.drawio
```

From the **repository root**, use e.g. `@public/examples/c1-context.puml`; from **`public/`**, use `@examples/c1-context.puml`.

**Same request with an inline body** (minimal **C1 / system context** PlantUML — useful when you want to see exactly what is posted; run in **bash**, **zsh**, or **Git Bash**; `@-` tells curl to read the body from stdin):

```bash
curl -sS -X POST "https://c4.wtx.pl/api/convert" \
  -H "Content-Type: text/plain; charset=utf-8" \
  --data-binary @- \
  -o diagram.drawio <<'EOF'
@startuml Minimal_C1_Context
!include https://raw.githubusercontent.com/plantuml-stdlib/C4-PlantUML/v2.10.0/C4_Context.puml

Person(user, "User", "Someone who uses the system")
System(system, "Software System", "Core application")
Rel(user, system, "Uses")

@enduml
EOF
```


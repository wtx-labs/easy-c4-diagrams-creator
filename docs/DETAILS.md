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
- `backend/` - conversion/validation logic and data (outside webroot)
- `docs/` - extra docs (deploy/how-to/notes), including **OpenAPI** `easy-c4-api-specification.yaml`

### Public instance and API usage

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

### `{"error":"Missing @startuml ... @enduml block."}` when using curl

The API only sees the **raw HTTP body**. That error means the body it parsed was **not** valid PlantUML (no `@startuml` … `@enduml`), often because the client never sent the file contents.

**Windows PowerShell:** `curl` is usually an alias for **`Invoke-WebRequest`**, not real curl. It does **not** treat `--data-binary "@file.puml"` like **curl.exe** (read file from disk). You may be posting a tiny literal string instead of the diagram, which triggers this error.

Use the real binary explicitly:

```powershell
curl.exe -sS -X POST "https://c4.wtx.pl/api/convert" `
  -H "Content-Type: text/plain; charset=utf-8" `
  --data-binary "@c1-context.puml" `
  -o diagram.drawio
```

(Run it from the folder that contains `c1-context.puml`, or use a full path after `@`, e.g. `@D:\path\to\public\examples\c1-context.puml`.)

**Checks:** open the `.puml` locally and confirm the first line starts with `@startuml` and the file ends with `@enduml`. If you use **`Content-Type: application/json`**, the PlantUML text must be inside **`"puml"`** or **`"source"`** — a raw file upload with a JSON content type will not work.

### API reference (machine-readable)

Full list of paths, methods, request/response shapes, and status codes: **`docs/easy-c4-api-specification.yaml`** (OpenAPI 3). The same file is copied to **`public/docs/easy-c4-api-specification.yaml`** so the live site footer can link to it; keep both in sync when editing.

Documented endpoints: **`/api/health`**, **`/api/convert`**, **`/api/validate`**. The browser UI calls validate and convert before download / open in diagrams.net.

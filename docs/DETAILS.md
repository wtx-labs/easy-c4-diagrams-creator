## EasyC4 Diagram Creator - Technical details

This document collects technical notes that are intentionally kept short in the main `README.md` - architecture summary, deployment pointers, project structure, and API endpoints.

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
- `docs/` - extra docs (deploy/how-to/notes)

### API (if you want to use it as a service)

- `GET /api/health` → `{ ok: true, shapes: <count> }`
- `POST /api/validate` → `{ ok, errors[], warnings[] }`
- `POST /api/convert` → `.drawio` as `application/xml` (attachment)
- `POST /api/preview-ir` → intermediate IR JSON (debug)

`POST /api/convert` accepts either:

- **raw text** in the request body (e.g. `Content-Type: text/plain; charset=utf-8`), or
- **JSON**: `{ "puml": "..." }` or `{ "source": "..." }`


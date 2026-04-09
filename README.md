## EasyC4 Diagram Creator

Convert **PlantUML C4** or **Mermaid C4** text into a **`.drawio`** diagram you can open in **diagrams.net / draw.io**.

> **🚀 Application available online - try it now!**
>
> **Just visit [https://c4.wtx.pl](https://c4.wtx.pl)**: 
>
> Paste your C4 source, convert, then **download** the `.drawio` file or **open it directly** in diagrams.net - in seconds.
>

[![c4.wtx.pl](/easy-c4-diagram-creator-screenshot.png)](https://c4.wtx.pl)

### What it’s for

- **Fast path from diagram-as-code to draw.io**: generate a solid baseline, then fine-tune layout, labels, and styling in the editor.
- **Consistent C4 visuals** powered by the [EasyC4](https://github.com/maciek365/c4-diagrams.net) shape library (Context/Container/Component).
- **Team-friendly workflow**: text inputs are easy to review in PRs, while `.drawio` is easy to open for anyone.

### About the C4 model

The [C4 model](https://c4model.com/) is a lightweight way to describe software architecture using a small set of diagram levels.
This tool focuses on the most common C4 views: **System Context (C1)**, **Container (C2)**, and **Component (C3)** - so you can go from text to a `.drawio` diagram quickly.

### Key features

- **Inputs**: PlantUML C4 (`@startuml … @enduml`) or Mermaid C4 (`C4Context` / `C4Container` / `C4Component`).
- **Validation**: helpful errors and warnings before conversion.
- **Export**: download a ready-to-edit **`.drawio`** (XML).
- **One‑click open**: “Open diagram in diagrams.net” (converts and opens in a new tab).
- **Upload / drag & drop**: load `.puml` / `.mmd` / `.txt` files or drop them into the editor.
- **Built-in examples**: ready samples for PlantUML and Mermaid (Context/Container/Component).
- **AI prompt helpers**: ready-made prompt snippets to generate compatible PlantUML/Mermaid C4.

### How it works (high level)

1. Paste C4 source (PlantUML or Mermaid) or load a file.
2. Convert and either **download** a `.drawio` file or **open** it in diagrams.net.

### HTTP API (automation)

You can generate a `.drawio` file **without the browser** by calling **`POST /api/convert`** with the same PlantUML or Mermaid C4 text as the request body (see `docs/DETAILS.md` and `docs/easy-c4-api-specification.yaml`). The public instance is **[c4.wtx.pl](https://c4.wtx.pl)**.

### Requirements

- **PHP 8.0+** (8.1+ recommended)

### Run locally

From the project root:

```bash
php -S 127.0.0.1:8000 -t public
```

Then open `http://127.0.0.1:8000`.

### More documentation

- **Deploy**: see `docs/DEPLOY.md`
- **Technical details (deploy/structure/API)**: see `docs/DETAILS.md`
- **OpenAPI (endpoints)**: see `docs/easy-c4-api-specification.yaml`

### License

MIT - see `LICENSE`.

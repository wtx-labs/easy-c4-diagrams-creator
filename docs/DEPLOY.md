## EasyC4 Diagram Creator - How to deploy?

This document explains how to deploy **EasyC4 Diagram Creator** on a typical **PHP hosting** setup.
It focuses on the required **webroot** configuration (the `public/` directory), the recommended folder layout (keeping `backend/` outside the webroot), and a simple way to run the app locally for testing.

### Webroot

- **Document root (webroot)**: `public/`
- The `public/` directory contains the only files that should be exposed publicly:
  - `index.html`, JS/CSS assets, images/fonts
  - `index.php` + `.htaccess` (PHP API + routing)

Everything else (server-side code and data) lives outside the webroot in `backend/`.

### Shared hosting (Apache, `public_html`)

If your hosting uses `public_html/` and you cannot change the document root:

- Upload the **contents of `public/`** into `public_html/`.
- Upload the **`backend/`** directory next to `public_html/` (one level above), so the paths look like:

```text
public_html/
  index.php
  index.html
  assets/
  ...
backend/
  src/
  data/
```

### Local run

```bash
php -S 127.0.0.1:8000 -t public
```


---
name: frontend-guard
description: Remind agent of the frontend-only rule — no backend changes without approval; flag backend needs in red
---

# Frontend Guard

This project enforces a **frontend-only default**. Before touching any file, check whether it is backend or frontend.

## Rule

| Allowed without approval | Requires approval first |
|---|---|
| `.css`, `.js` (client-side), HTML/template markup, layout, colours, spacing | `.php`, SQL, DB schema, session/auth logic, API endpoints, server config |

## When a backend change is needed

Print this block before doing anything else, then **stop and wait**:

```
echo -e "\e[31m╔══════════════════════════════════════════════╗\e[0m"
echo -e "\e[31m║  BACKEND CHANGE NEEDED — approval required   ║\e[0m"
echo -e "\e[31m╚══════════════════════════════════════════════╝\e[0m"
echo "Files that would be modified:"
echo "  - <list each backend file here>"
echo "Reason: <one line explaining why>"
echo ""
echo "Reply YES to proceed or describe an alternative."
```

Do NOT edit the file. Do NOT plan the edit. Wait for the user's explicit yes.

## Frontend-only checklist (no approval needed)

1. CSS changes (colours, spacing, fonts, layout) in any `.css` or inline `<style>`.
2. HTML structure changes that do not alter PHP logic or DB queries.
3. Client-side JS (animations, DOM manipulation, UI state).
4. Asset swaps (images, icons).

## Gotchas

- `dashboard.php` mixes HTML and PHP — editing the HTML sections is frontend; editing `<?php ... ?>` blocks is backend. When in doubt, flag it.
- Inline SQL inside a `.php` file is always backend, even if surrounded by HTML.
- Adding a `class=` attribute is frontend. Adding a form `action=` that changes a PHP handler is backend.

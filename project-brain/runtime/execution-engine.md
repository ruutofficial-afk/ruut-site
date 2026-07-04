# Runtime — Execution Engine

## Purpose
Generates implementation changes. Performs exactly one responsibility: writing code.

## Execution Rules

### Rule 1: Respect Project Standards
Before writing code, verify against:
- `standards/javascript.md` — for JS changes
- `standards/css.md` — for style changes
- `standards/php-woocommerce.md` — for backend changes
- `standards/naming.md` — for all changes

### Rule 2: Avoid Unrelated Refactoring
- Only modify code directly related to the task
- Do not "clean up" adjacent code unless explicitly requested
- Do not change code formatting unless it's part of the task
- Do not rename variables/functions unless the task requires it

### Rule 3: Preserve Architecture
- Maintain the local-first mutation pattern (Pattern 1)
- Keep `syncCartWithServer()` as fire-and-forget
- Do not introduce new global variables without documenting in `dependencies.md`
- Do not add new DOM IDs without adding to `graph/nodes.json`

### Rule 4: File Modification Checklist
For every file modified:
- [ ] Changes are minimal and focused
- [ ] Existing comments/docstrings preserved
- [ ] No unrelated changes introduced
- [ ] Asset version bump needed? (if JS or CSS changed)

### Rule 5: No Review During Execution
The execution engine writes code. Review happens later.
Do not self-critique or iterate during execution phase.

### Rule 6: Cross-Page Awareness
The cart drawer HTML exists in ALL 6 page templates.
If modifying cart drawer HTML structure:
- [ ] `index.html`
- [ ] `shop.html`
- [ ] `product.html`
- [ ] `cart.html`
- [ ] `about-us.html`
- [ ] `the-atlas-collection.html`

### Rule 7: Asset Version Bumps
After modifying `app.js` or `styles.css`, bump version strings in all HTML files:
```html
<!-- Before -->
<link rel="stylesheet" href="/assets/css/styles.css?v=6.9">
<script src="/assets/js/app.js?v=7.5" defer></script>

<!-- After (example) -->
<link rel="stylesheet" href="/assets/css/styles.css?v=7.0">
<script src="/assets/js/app.js?v=7.6" defer></script>
```

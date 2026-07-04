# Standards — Documentation

## Asset Version Bumping
After modifying `app.js` or `styles.css`, version strings MUST be bumped in ALL 6 HTML files:

**Files to update**:
1. `index.html`
2. `shop.html`
3. `product.html`
4. `cart.html`
5. `about-us.html`
6. `the-atlas-collection.html`

**Pattern**:
```html
<!-- CSS version bump -->
<link rel="stylesheet" href="/assets/css/styles.css?v=X.Y">

<!-- JS version bump -->
<script src="/assets/js/app.js?v=X.Y" defer></script>
```

**Current versions**: `styles.css?v=6.9`, `app.js?v=8.0`

## Memory File Updates
After every accepted code change:
1. Update affected memory files in `project-brain/memory/`
2. Keep each file under 500 lines
3. Only update the specific sections that changed
4. Preserve all unrelated content

## Graph Updates
After every accepted code change:
1. Add new nodes to `nodes.json`
2. Add new edges to `edges.json`
3. Update `graph-index.json` lookup tables
4. Update `graph.json` summary counts

## Task History
Every completed task must be logged in `tasks/completed.md`:
```markdown
### [Date] — [Task Title]
- **Goal**: [one sentence]
- **Files Changed**: [list]
- **Modules Changed**: [list from graph]
- **Review Score**: [overall score]
- **Memory Updated**: [list of memory files]
- **Graph Updated**: [yes/no, what changed]
```

## Code Comments
- Preserve all existing comments when modifying code
- Add comments for non-obvious logic
- Use `// RUUT:` prefix for custom annotations
- Document `why`, not `what`

## Handoff Documentation
When making significant changes, update:
- `DEVELOPER_HANDOFF.md` — if the change affects developer onboarding
- `architecture.md` (project root) — if the architectural overview changes

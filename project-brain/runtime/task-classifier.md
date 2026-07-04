# Runtime — Task Classifier

## Purpose
Classifies incoming prompts by complexity, category, and required review depth.

## Classification Dimensions

### 1. Complexity Level

| Level | Criteria | Examples |
|-------|----------|---------|
| **Low** | 1 file, 1 function, no side effects, < 20 lines changed | Fix typo in cart text, update promo copy, change button color |
| **Medium** | 2-4 files, managed side effects, 20-100 lines | Add new coupon code, change progress bar threshold, modify upsell logic |
| **High** | 5+ files, cross-layer changes, 100+ lines | Add new payment method, refactor cart state management, add cart persistence |
| **Critical** | Core architecture, data model changes, breaking API changes | Change localStorage schema, modify AJAX endpoint contracts, remove WooCommerce dependency |

### 2. Task Category

| Category | Description | Example |
|----------|-------------|---------|
| **Bug Fix** | Something broken needs fixing | "Cart badge shows wrong count" |
| **Feature** | New functionality | "Add wishlist to mini-cart" |
| **Refactor** | Restructure without changing behavior | "Extract coupon logic into separate module" |
| **Style** | Visual/CSS changes only | "Make cart drawer wider on desktop" |
| **Config** | Configuration/settings changes | "Change reward threshold from 3 to 4" |
| **Performance** | Optimization without feature changes | "Reduce DOM reflows in renderCart" |

### 3. Affected Layers

| Layer | Indicators |
|-------|-----------|
| Frontend Only | Changes to `app.js`, `cart.html`, `styles.css` |
| Backend Only | Changes to `ruut-core.php` |
| Full Stack | Changes spanning both frontend and backend |
| Cross-Page | Changes to cart drawer HTML (all 6 templates) |

## Classification Output
```markdown
**Complexity**: Medium
**Category**: Feature
**Affected Layers**: Frontend Only
**Review Depth**: Standard (code-quality + architecture)
**Estimated Files**: 2 (app.js, styles.css)
**Risk Level**: Low
```

## Quick Classification Shortcuts

| Prompt Contains | Likely Classification |
|----------------|----------------------|
| "fix", "broken", "not working" | Bug Fix, Medium complexity |
| "add", "new", "implement" | Feature, Medium-High complexity |
| "change", "update", "modify" | Config/Style, Low-Medium complexity |
| "refactor", "restructure", "clean up" | Refactor, Medium-High complexity |
| "slow", "optimize", "performance" | Performance, Medium complexity |
| "WooCommerce", "AJAX", "server" | Full Stack, Medium-High complexity |

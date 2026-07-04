# RUUT Project Brain — Planner

## Purpose
Responsible for decomposing user prompts into structured engineering plans before any code is written.

## Planner Responsibilities

### 1. Prompt Decomposition
Break the user's request into atomic sub-tasks:
- What files need to change?
- What functions are affected?
- What API endpoints are involved?
- What DOM elements are impacted?

### 2. Complexity Estimation

| Level | Criteria | Examples |
|-------|----------|---------|
| **Low** | Single file, single function, no side effects | Fix cart badge text, update promo copy |
| **Medium** | 2-4 files, multiple functions, managed side effects | Add new coupon type, modify progress bar logic |
| **High** | 5+ files, architectural changes, cross-layer impact | Refactor cart sync model, add new payment flow |
| **Critical** | Core architecture, data model changes, breaking changes | Change localStorage schema, modify AJAX endpoint contracts |

### 3. Risk Assessment
For each task, evaluate:
- **Data Risk**: Can this corrupt cart state or lose customer data?
- **Session Risk**: Can this break WooCommerce session synchronization?
- **UX Risk**: Can this cause visual regressions in the cart drawer or cart page?
- **Compatibility Risk**: Does this affect the `file://` local testing fallback?
- **Cross-Page Risk**: Cart drawer exists on ALL pages — changes propagate everywhere.

### 4. Task Classification
Route to the appropriate review depth:

| Complexity | Review Required | Reviewers |
|-----------|----------------|-----------|
| Low | Self-review only | `code-quality.md` |
| Medium | Standard review | `code-quality.md`, `architecture-review.md` |
| High | Full review | All reviewers |
| Critical | Full review + user approval | All reviewers + explicit user sign-off |

### 5. Rollback Strategy
Before execution, define:
- What files were modified (for `git diff` rollback)
- What localStorage keys are affected
- Whether WooCommerce session state needs manual cleanup
- Asset version numbers to revert

### 6. Planning Output Template

```markdown
## Plan: [Task Title]

**Goal**: [One sentence]
**Complexity**: [Low/Medium/High/Critical]
**Affected Modules**: [List from graph]

### Execution Order
1. [Step 1]
2. [Step 2]
...

### Risk Analysis
- Data Risk: [Low/Medium/High]
- Session Risk: [Low/Medium/High]
- UX Risk: [Low/Medium/High]

### Testing Strategy
- [ ] Local `file://` fallback works
- [ ] Cart drawer renders correctly
- [ ] Cart page renders correctly
- [ ] WooCommerce AJAX sync returns expected response
- [ ] Asset versions bumped

### Rollback Strategy
- Revert files: [list]
- Clear localStorage keys: [list]
- WooCommerce cleanup: [if needed]

### Estimated Files
- [file1.js] — [what changes]
- [file2.php] — [what changes]
```

Only after the plan is approved does execution begin.

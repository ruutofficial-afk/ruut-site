# Runtime — Review Engine

## Purpose
Evaluates code changes after static validation passes.
References review checklists in `reviews/` and standards in `standards/`.

## Review Process

### Step 1: Select Applicable Reviewers
Based on task classification and affected files:

| Files Changed | Reviewers |
|---------------|-----------|
| `app.js` | `code-quality.md`, `performance-review.md` |
| `styles.css` | `code-quality.md`, `performance-review.md` |
| `ruut-core.php` | `code-quality.md`, `security-review.md` |
| `cart.html` | `code-quality.md`, `architecture-review.md` |
| Multiple files | `architecture-review.md`, `documentation-review.md` |
| New feature | All reviewers |

### Step 2: Execute Each Reviewer
For each selected reviewer, evaluate the changes against its checklist.
Record pass/fail for each checklist item.

### Step 3: Generate Confidence Scores
Score each dimension on a 0-100 scale:

| Dimension | Weight | Criteria |
|-----------|--------|----------|
| Architecture | 20% | Follows established patterns, no coupling violations |
| Performance | 15% | No unnecessary DOM reads, efficient rendering |
| Security | 20% | Input sanitized, no XSS vectors, CORS respected |
| Naming | 10% | Follows `ruut_` prefix convention, descriptive names |
| Documentation | 15% | Memory files updated, comments preserved |
| Testing | 20% | Testable locally, fallbacks work, edge cases handled |

### Step 4: Calculate Overall Score
```
Overall = Σ(dimension_score × weight)
```

### Step 5: Decision Gate
- **Overall ≥ 90**: Accept → proceed to Knowledge Synchronization
- **Overall 80-89**: Conditional accept → fix specific issues, re-review
- **Overall < 80**: Reject → return to Execution with improvement targets

### Review Output Template
```markdown
## Review: [Task Title]

### Scores
| Dimension | Score |
|-----------|-------|
| Architecture | 96 |
| Performance | 91 |
| Security | 98 |
| Naming | 95 |
| Documentation | 90 |
| Testing | 92 |
| **Overall** | **93** |

### Issues Found
- [Issue 1]: [Description] — Severity: [Low/Medium/High]

### Recommendations
- [Recommendation 1]

### Decision: ACCEPT / CONDITIONAL / REJECT
```

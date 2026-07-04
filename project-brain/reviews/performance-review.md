# Review — Performance

## Checklist
- [ ] **DOM Reads Minimized**: Are DOM element lookups cached in variables (not re-queried in loops)?
- [ ] **Render Efficiency**: Does `renderCart()` avoid unnecessary full re-renders?
- [ ] **Network Calls**: Are AJAX calls fire-and-forget? No blocking on server response for UI?
- [ ] **Product Catalog Cache**: Is `ensureProductsFetched()` used instead of direct API calls?
- [ ] **Animation Performance**: Are animations using `transform` and `opacity` (GPU-composited)?
- [ ] **Image Loading**: Are images using `loading="lazy"` and `decoding="async"`?
- [ ] **Bundle Size**: No unnecessary libraries or dependencies added?
- [ ] **Reflow Prevention**: No forced synchronous layouts in loops?

## Scoring
| Score | Criteria |
|-------|----------|
| 95-100 | Optimal performance, no unnecessary reflows, efficient caching |
| 85-94 | Minor performance issues, acceptable for current scale |
| 70-84 | Noticeable performance regressions, needs optimization |
| < 70 | Significant performance problems, blocks merge |

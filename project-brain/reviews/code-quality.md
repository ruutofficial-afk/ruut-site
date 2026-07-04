# Review — Code Quality

## Checklist
- [ ] **Naming**: Follows conventions in `standards/naming.md`?
- [ ] **Readability**: Code is self-documenting? Complex logic has comments?
- [ ] **DRY**: No duplicated logic? Shared patterns extracted?
- [ ] **Error Handling**: try/catch for localStorage, silent catch for AJAX sync?
- [ ] **Edge Cases**: Empty cart? Zero quantity? Invalid coupon? Network timeout?
- [ ] **Null Safety**: DOM elements checked with `if (element)` before use?
- [ ] **Existing Comments**: All existing comments preserved?
- [ ] **Console Logging**: Only meaningful `console.error` for debugging, no `console.log` spam?
- [ ] **Intent Match**: Does the implementation actually solve the stated problem?
- [ ] **Side Effects**: Any unintended changes to other cart features?

## Scoring
| Score | Criteria |
|-------|----------|
| 95-100 | Clean, readable, well-structured, handles edge cases |
| 85-94 | Minor style issues, good structure, mostly handles edge cases |
| 70-84 | Noticeable quality issues, some edge cases missed |
| < 70 | Poor quality, major issues, blocks merge |

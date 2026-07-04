# Review — Architecture

## Checklist
- [ ] **Pattern Adherence**: Does the change follow the local-first mutation pattern?
- [ ] **Separation of Concerns**: Cart rendering separate from data mutation? Server sync separate from UI?
- [ ] **State Ownership**: Does the change respect the state ownership table (localStorage vs WooCommerce)?
- [ ] **Cross-Page Impact**: If cart drawer HTML changed, are all 6 templates updated?
- [ ] **No New Globals**: Were any new `window.*` variables introduced? If yes, are they documented?
- [ ] **No Tight Coupling**: Does the change avoid coupling cart logic to specific page layouts?
- [ ] **Graceful Degradation**: Does the `file://` protocol fallback still work?
- [ ] **Single Responsibility**: Each function does one thing?

## Scoring
| Score | Criteria |
|-------|----------|
| 95-100 | Follows all patterns, clean separation, no side effects |
| 85-94 | Minor deviations, acceptable coupling, documented trade-offs |
| 70-84 | Noticeable coupling or pattern violations, needs refactoring |
| < 70 | Architectural violations, unacceptable for merge |

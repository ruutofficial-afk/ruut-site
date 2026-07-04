# Review — Security

## Checklist
- [ ] **XSS Prevention**: No raw user input in `innerHTML` without escaping?
- [ ] **Input Sanitization (PHP)**: All `$_POST` values sanitized with `intval()` or `sanitize_text_field()`?
- [ ] **SQL Injection**: No raw SQL queries? Using WooCommerce/WordPress APIs?
- [ ] **Authentication**: Sensitive endpoints require proper auth?
- [ ] **Authorization**: Cart operations available to both logged-in and guest users?
- [ ] **Secret Exposure**: No API keys, passwords, or tokens in frontend code?
- [ ] **Error Messages**: No server paths or stack traces exposed in production responses?
- [ ] **CORS**: Origin whitelist maintained? `credentials: 'include'` used?
- [ ] **Dependency Risks**: No new third-party scripts introduced?
- [ ] **localStorage**: No sensitive data stored?

## Scoring
| Score | Criteria |
|-------|----------|
| 95-100 | All inputs sanitized, no XSS vectors, CORS correct |
| 85-94 | Minor oversights, no exploitable vulnerabilities |
| 70-84 | Potential vulnerabilities found, needs fixing |
| < 70 | Active security risks, blocks merge |

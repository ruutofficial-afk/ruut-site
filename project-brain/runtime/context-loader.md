# Runtime — Context Loader

## Purpose
Loads only the relevant memory files based on the task classification and graph retrieval results.

## Loading Rules

### Rule 1: Never Load All Memory
Maximum memory files loaded per session: **4 out of 8**

### Rule 2: Mapping Table
Based on affected graph nodes, load the corresponding memory files:

| Affected Node Types | Memory Files to Load |
|---------------------|---------------------|
| JavaScript functions | `frontend.md` |
| DOM elements | `frontend.md` |
| PHP functions / AJAX handlers | `backend.md` |
| API endpoints | `api.md` |
| URL patterns / redirects | `routing.md` |
| localStorage / CSS vars / assets | `dependencies.md` |
| Code patterns / conventions | `patterns.md` |
| System-level / multi-layer | `architecture.md` |
| First session / new agent | `overview.md` + `architecture.md` |

### Rule 3: Cache-First Loading
Before loading from memory files, check `cache/recent-context.md`:
- If the previous task touched the same nodes → reuse cached context
- Only load fresh memory for newly affected nodes

### Rule 4: Context Budget
Target: **Under 2000 lines** of context per session.
If context exceeds budget:
1. Load only the specific function/section from memory files
2. Reference line numbers rather than full file content
3. Summarize patterns rather than providing full examples

## Example: "Fix cart badge not updating"

**Graph retrieval returns**: `fn_renderCart`, `dom_cartCount`

**Context loaded**:
1. `frontend.md` — specifically the `renderCart()` section and DOM elements table
2. `patterns.md` — Pattern 8 (Cart Badge Pop Animation)
3. `dependencies.md` — CSS properties table (for `--ruut-accent`)

**Not loaded**: `backend.md`, `api.md`, `routing.md` (not relevant)

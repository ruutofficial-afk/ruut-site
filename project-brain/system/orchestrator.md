# RUUT Project Brain — Orchestrator

## Purpose
Every user prompt follows this exact deterministic pipeline.
The orchestrator coordinates all runtime components in sequence.

## The 10-Stage Pipeline

### Stage 1: Receive Prompt
- Accept user input
- Check `cache/recent-context.md` for continuity with previous tasks
- If sequential prompt detected, preload cached context

### Stage 2: Task Classification
- Invoke: `runtime/task-classifier.md`
- Output: Complexity level (Low / Medium / High / Critical)
- Output: Task category (Bug Fix / Feature / Refactor / Style / Config)

### Stage 3: Graph Retrieval
- Invoke: `runtime/graph-retriever.md`
- Input: Keywords and intent from the prompt
- Output: Affected nodes, connected files, dependent APIs

### Stage 4: Context Loading
- Invoke: `runtime/context-loader.md`
- Input: Affected nodes from Stage 3
- Output: Relevant memory files loaded into session context
- Rule: Never load all memory files — only those connected to affected nodes

### Stage 5: Planning
- Invoke: `system/planner.md`
- Output: Structured plan with goal, affected modules, execution order, risks, testing strategy
- Gate: For High/Critical tasks, require user approval before proceeding

### Stage 6: Execution
- Invoke: `runtime/execution-engine.md`
- Input: Approved plan + loaded context
- Output: Code changes
- Rule: No review occurs during execution

### Stage 7: Static Validation
- Run deterministic checks:
  - JavaScript: No syntax errors, no console.error patterns
  - PHP: `php -l` syntax check on modified files
  - HTML: Valid structure, no unclosed tags
  - CSS: No invalid property warnings
- If ANY validation fails → return to Stage 6
- AI review is skipped on validation failure

### Stage 8: AI Review
- Invoke: `runtime/review-engine.md`
- Only executes after Stage 7 passes
- Evaluates against applicable checklists in `reviews/`
- References standards in `standards/`

### Stage 9: Confidence Scoring
- Score each dimension (0-100):
  - Architecture
  - Performance
  - Security
  - Naming
  - Documentation
  - Testing
- Calculate Overall score (weighted average)
- Acceptance threshold: **Overall ≥ 90**
- If below threshold: return to Stage 6 with specific improvement targets

### Stage 10: Knowledge Synchronization
- Invoke: `runtime/memory-updater.md` — update affected memory files
- Invoke: `runtime/graph-updater.md` — update graph nodes/edges
- Append to `tasks/completed.md` or `tasks/failed.md`
- Update `tasks/changelog.md`
- Refresh `cache/` files for next session

## Pipeline Integrity Rules
1. **No stage may be skipped** — even for "simple" tasks.
2. **Stages 7-9 are cost-optimized** — static checks run before expensive AI review.
3. **Knowledge sync is mandatory** — every accepted change improves the system.
4. **Cache accelerates sequential work** — reduces graph retrieval overhead.

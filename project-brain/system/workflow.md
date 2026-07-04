# RUUT Project Brain — Workflow

## Purpose
Defines the deterministic execution lifecycle that every user prompt follows.
No implementation logic exists in this file.

## Execution Lifecycle

```
Receive Prompt
     │
     ▼
Task Classification (runtime/task-classifier.md)
     │
     ▼
Graph Retrieval (runtime/graph-retriever.md)
     │  ── Find affected nodes
     │  ── Resolve dependencies
     │  ── Return connected files, APIs, routes, components
     │
     ▼
Context Loading (runtime/context-loader.md)
     │  ── Load only relevant memory files
     │  ── Check cache for recent context
     │
     ▼
Planning (system/planner.md)
     │  ── Decompose prompt
     │  ── Estimate complexity
     │  ── Assess risks
     │  ── Define rollback strategy
     │
     ▼
Execution (runtime/execution-engine.md)
     │  ── Modify code
     │  ── Respect project standards
     │  ── Avoid unrelated refactoring
     │  ── Preserve architecture
     │
     ▼
Static Validation
     │  ── Browser console check (no JS errors)
     │  ── PHP syntax validation
     │  ── HTML structure validation
     │
     ▼
AI Review (runtime/review-engine.md)
     │  ── Only runs AFTER static validation passes
     │  ── Evaluates against reviews/ checklists
     │
     ▼
Confidence Scoring
     │  ── Quantitative scoring per dimension
     │  ── Threshold: Overall ≥ 90
     │
     ├── Score < 90 → Improve → Re-review
     │
     ▼
Score ≥ 90
     │
     ▼
Knowledge Synchronization
     │  ── Update affected memory files (runtime/memory-updater.md)
     │  ── Update graph (runtime/graph-updater.md)
     │  ── Append task history (tasks/)
     │  ── Update cache (cache/)
     │
     ▼
Return Final Response
```

## Rules
1. No stage may be skipped.
2. Static validation runs BEFORE AI review to minimize token consumption.
3. If static validation fails, execution returns to coding phase — AI review is skipped.
4. Knowledge synchronization only occurs for accepted changes.
5. Cache is updated on every completed task to accelerate sequential prompts.

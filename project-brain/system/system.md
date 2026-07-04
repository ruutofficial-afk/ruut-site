# RUUT Project Brain — System Definition

## Purpose
This file defines the global engineering workflow for the RUUT e-commerce cart system.
It governs **how the AI thinks**, not what the project contains.

## Identity
- Project: RUUT E-Commerce (yourruut.com)
- Architecture: Decoupled headless — static HTML/CSS/JS frontend + WordPress/WooCommerce backend
- Domain Scope: Cart Drawer (mini-cart), Standalone Cart Page, WooCommerce AJAX Synchronization

## Non-Negotiable Principles

1. **Never analyze the entire codebase** unless explicitly requested.
2. **Always retrieve only the required context** via the graph layer.
3. **Every prompt follows the same deterministic execution pipeline** defined in `workflow.md`.
4. **Memory is modular** — load only the memory files relevant to the task.
5. **Graph retrieval is the primary source** of project context.
6. **Documentation updates automatically** after accepted changes.
7. **Knowledge grows incrementally** with every completed task.

## Responsibilities of This File
- Receive user prompts
- Enforce execution order (delegate to `workflow.md`)
- Route to the correct runtime components

## What This File Must NOT Contain
- Architecture details (see `memory/architecture.md`)
- Project memory (see `memory/` directory)
- Implementation details (see `memory/frontend.md`, `memory/backend.md`)
- Standards or quality rules (see `standards/` directory)

## Execution Entry Point
When a new prompt is received:
1. Read `system/orchestrator.md` for the full pipeline
2. Classify the task via `runtime/task-classifier.md`
3. Retrieve context via `runtime/graph-retriever.md`
4. Load relevant memory via `runtime/context-loader.md`
5. Proceed through planning → execution → review → sync

## Stability Notice
This file should remain stable throughout the lifetime of the project.
Changes to this file require explicit user approval.

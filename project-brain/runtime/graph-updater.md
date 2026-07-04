# Runtime — Graph Updater

## Purpose
After an accepted change, incrementally update graph nodes and edges.
The AI must never manually edit graph structures outside of this process.

## When to Update

### New Node Required
- New JavaScript function added → add to `nodes.json` with type `function`
- New DOM element with ID → add to `nodes.json` with type `dom_element`
- New AJAX action → add to `nodes.json` with type `api_endpoint`
- New PHP handler → add to `nodes.json` with type `function`
- New localStorage key → add to `nodes.json` with type `storage_key`

### New Edge Required
- Function A now calls Function B → add edge with type `calls`
- Function renders new DOM element → add edge with type `renders`
- Function reads from storage → add edge with type `reads_from`
- Function writes to storage → add edge with type `writes_to`
- Function syncs with API → add edge with type `syncs_with`
- DOM event triggers function → add edge with type `triggers`

### Node Removal
- Function removed → remove from `nodes.json`
- DOM element removed → remove from `nodes.json`
- Remove all edges referencing the deleted node from `edges.json`

### Edge Modification
- Function A no longer calls Function B → remove the edge
- Function now calls a different function → update the edge

## Update Process
1. Read current `nodes.json` and `edges.json`
2. Apply minimal changes (add/remove/modify)
3. Update `graph-index.json` lookup tables
4. Update `graph.json` summary counts
5. Validate: no orphan edges (edges referencing non-existent nodes)

## Example: Adding a new `clearCart()` function
```json
// Add to nodes.json:
{ "id": "fn_clearCart", "type": "function", "file": "assets/js/app.js", "lines": "XXX-XXX", "label": "clearCart()", "scope": "window" }

// Add to edges.json:
{ "from": "fn_clearCart", "to": "fn_renderCart", "type": "calls", "label": "Re-renders empty cart" },
{ "from": "fn_clearCart", "to": "storage_ruut_cart", "type": "writes_to", "label": "Clears localStorage" },
{ "from": "fn_clearCart", "to": "fn_syncCartWithServer", "type": "calls", "label": "Syncs empty cart" }

// Update graph-index.json by_name:
"clearCart": "fn_clearCart"

// Update graph.json summary:
"total_nodes": 43,  // was 42
"total_edges": 61   // was 58
```

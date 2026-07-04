# Runtime — Graph Retriever

## Purpose
Queries the graph to find the minimum context required for a given task.

## Retrieval Algorithm

### Step 1: Extract Keywords
From the user prompt, extract:
- Function names (e.g., "addToCart", "renderCart")
- DOM element references (e.g., "cart badge", "progress bar")
- API references (e.g., "coupon", "add to cart")
- File references (e.g., "app.js", "cart.html", "ruut-core.php")

### Step 2: Match Entry Nodes
Search `graph-index.json` to find matching node IDs:
- `by_name` — for function/variable names
- `by_dom_id` — for DOM element references
- `by_api` — for API action references
- `by_file` — for file-level references

### Step 3: Resolve Dependencies (1 hop)
For each matched node, find all directly connected edges in `edges.json`:
- Outgoing edges: what does this node call/render/write?
- Incoming edges: what calls/triggers this node?

### Step 4: Resolve Dependencies (2 hops for High/Critical)
For High and Critical complexity tasks, extend to 2-hop neighbors:
- What do the 1-hop neighbors depend on?
- This catches indirect side effects

### Step 5: Return Context Package
Output:
```json
{
  "affected_nodes": ["fn_renderCart", "dom_cartCount"],
  "connected_files": ["assets/js/app.js"],
  "connected_apis": [],
  "connected_storage": ["storage_ruut_cart"],
  "memory_files_to_load": ["frontend.md", "patterns.md"],
  "estimated_impact": "Low"
}
```

## Example Retrievals

### "Fix cart badge not updating"
```
Keywords: cart badge, not updating
Matched nodes: dom_cartCount
1-hop: fn_renderCart (renders dom_cartCount)
1-hop: fn_addToCart → fn_renderCart (calls)
Result: fn_renderCart, dom_cartCount, fn_addToCart
Memory: frontend.md
```

### "Add dark mode to mini-cart"
```
Keywords: dark mode, mini-cart
Matched nodes: dom_cartDrawer, dom_cartItemsList
1-hop: fn_renderCart (renders both)
1-hop: fn_openCart, fn_closeCart (toggle drawer)
Result: dom_cartDrawer, dom_cartItemsList, fn_renderCart, fn_openCart
Memory: frontend.md, dependencies.md (CSS vars)
```

### "Cart not syncing with WooCommerce"
```
Keywords: sync, WooCommerce
Matched nodes: fn_syncCartWithServer
1-hop: api_ruut_add_to_cart, api_ruut_update_cart_qty (syncs_with)
1-hop: fn_ruutFetch (calls)
2-hop: php_ruut_ajax_add_to_cart, php_ruut_ensure_cart
Result: Full sync chain
Memory: frontend.md, backend.md, api.md, patterns.md
```

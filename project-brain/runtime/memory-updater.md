# Runtime — Memory Updater

## Purpose
After an accepted change, incrementally update only the affected memory files.

## Rules

### Rule 1: Never Regenerate Entire Documentation
Only update the specific sections that changed.

### Rule 2: Update Triggers
A memory update is required when:
- A new function is added → update `frontend.md` or `backend.md`
- A function signature changes → update the relevant memory file
- A new DOM element is added → update `frontend.md` DOM Elements table
- A new API endpoint is added → update `api.md` and `backend.md`
- A new localStorage key is added → update `dependencies.md`
- A new CSS property is used → update `dependencies.md`
- A new pattern emerges → update `patterns.md`
- Architecture changes → update `architecture.md`
- Asset versions bump → update `dependencies.md` version table

### Rule 3: Section-Level Updates
When updating a memory file:
1. Identify the exact section that needs modification
2. Update only that section
3. Preserve all other content unchanged
4. Add a comment if the change is non-obvious

### Rule 4: Line Count Check
After updating, verify the file stays under 500 lines.
If approaching the limit:
- Consider splitting into sub-files
- Remove outdated/deprecated information
- Compress verbose examples

## Update Template
When documenting a memory update in `tasks/changelog.md`:
```markdown
### Memory Update — [Date]
- **File**: memory/[filename].md
- **Section**: [section name]
- **Change**: [what was added/modified/removed]
- **Reason**: [task that triggered the update]
```

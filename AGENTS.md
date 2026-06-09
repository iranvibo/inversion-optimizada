# AGENTS.md — Knowledge Management System

## Context

> Fill this section when deploying this system in a specific environment. All agents must read this before operating.

```
Company / Project : Inversión Optimizada
Domain            : ViBo Invest (Automated Trading Platform for Retail Users)
Primary language  : Spanish (Conversations and documentation)
Main users        : Retail/non-technical users (aged 35–55, seeking simple automated trading with risk control)
Key constraints   : 
  - Extreme simplicity: Styled as the "Netflix of automated trading", avoiding complex terminal designs.
  - Minimalist UX/UI: No advanced technical indicators (RSI, MACD, candles, order books). Use clear, human-readable terms (e.g. "caídas temporales" instead of "volatilidad").
  - Security & Trust: Highlight risk management features (daily stop loss, protected capital limit, no withdrawal permissions).
  - Integration: Binance exchange integration via API.
Notes             : Keep features limited for the MVP (login, simulation, connecting Binance, activating/pausing bot, and showing balance evolution).
```

---

## Agent Lifecycle: The Start and the End

To ensure memory remains useful, every agent **MUST** follow this protocol:

1.  **Phase: Retrieval (Start of task)**: Read `memory/MEMO.md` before doing anything else.
2.  **Phase: Implementation**: While coding, take note of decisions, non-obvious fixes, and new patterns.
3.  **Phase: Memory Update (End of task)**: **CRITICAL.** Before saying "I'm done" or ending the task, you **MUST** evaluate if the work done falls into P1 or P2 categories of the Priority Table. If it does, update the memory **immediately**.

> [!IMPORTANT]
> A task is **NOT** finished until the relevant knowledge has been stored or updated in the `memory/` directory.

---

## Directory Structure

```
/
├── AGENTS.md          ← You are here. Instructions for all agents.
└── memory/
    ├── MEMO.md        ← Index of all direct knowledge files and subfolders in /memory
    ├── <topic>.md     ← Knowledge files, named by slug, with frontmatter header
    └── <topic>/       ← Subfolder for complex topics
        └── MEMO.md    ← Index of files within that subfolder
```

---

## How to Retrieve Knowledge

1. Read `memory/MEMO.md`.
2. For each entry, check if the **description or tags** match the current topic.
3. Read **only** the file(s) that match — do not read others.
4. If an entry points to a **subfolder**, read the `MEMO.md` inside that subfolder to find the specific file. Never guess filenames inside subfolders.
5. If nothing matches, assume no prior knowledge exists on the topic.

> Token efficiency rule: `MEMO.md` is designed to be small. Read it fully, then read only what you need. Never scan all files in `/memory` directly.

---

## How to Save New Knowledge

**Step 1 — Decide if it's worth saving** using the priority table below.

**Step 2 — Check for duplicates**: read `memory/MEMO.md` and scan descriptions and tags.

**Step 3a — No match found**: create `memory/<slug>.md` with frontmatter, write the knowledge, add an entry to `memory/MEMO.md`.

**Step 3b — Match found**: follow "How to Update Knowledge" below.

File naming: lowercase slugs with hyphens. Examples: `python-async.md`, `docker-networking.md`, `user-preferences.md`.

### When to Save — Priority Table

| Priority | Save? | Examples |
|---|---|---|
| P1 — Always | Yes | Decisions + their reasons, user preferences and habits, bugs that took time to diagnose, non-obvious configuration |
| P2 — If non-trivial | Yes | Patterns validated through trial and error, cross-system integration details, performance tradeoffs discovered |
| P3 — Skip | No | Facts findable via search in <30s, one-off commands, info already in the codebase or official docs |

When in doubt between P2 and P3: ask yourself *"would re-deriving this cost more than 5 minutes?"*. If yes, save it.

---

## How to Update Knowledge

1. Identify the file via `memory/MEMO.md`.
2. Read the existing file.
3. Apply the appropriate merge strategy (see table below).
4. Update the `updated` date in the frontmatter.
5. If the MEMO.md description or tags no longer accurately reflect the file, update that entry too.
6. Never keep outdated information — delete stale content directly.

### Merge Strategy

| Situation | Action |
|---|---|
| New info **extends** existing content | Add a new section or append to the relevant section |
| New info **contradicts** existing content | Replace old content with new; keep the reason if it explains a decision |
| New info **overlaps** (same topic, different angle) | Read both, combine without duplicating, keep the most complete version |
| Genuinely **different subtopic** under the same slug | Consider splitting into a subfolder if it would exceed ~150 lines |

When contradicting info comes in: trust the newer information unless there's explicit context saying the old version applies to a different environment or constraint.

---

## When to Create a Subfolder

Create a subfolder `memory/<topic>/` when **any of the following** apply:

- A single file for the topic would exceed ~150 lines.
- The topic has 3 or more clearly distinct subtopics that each require meaningful explanation.
- The topic is ongoing (e.g., a project, a technology stack) and is expected to grow.

When creating a subfolder:
1. Create `memory/<topic>/MEMO.md` as the index for files within it.
2. Add one entry in `memory/MEMO.md` pointing to the folder — not to individual files inside it.
3. The entry description and tags should summarize the topic so agents know whether to enter the folder.

---

## MEMO.md Format

Each `MEMO.md` uses a flat list. One line per entry:

```
- [filename-or-folder](relative-path) — description | tags: tag1, tag2, tag3
```

Example:
```
- [python-async.md](python-async.md) — async/await patterns, event loop, common pitfalls | tags: python, async, concurrency
- [docker-networking.md](docker-networking.md) — bridge vs host networks, container DNS, compose | tags: docker, networking, devops
- [react/](react/) — React ecosystem: hooks, state management, performance patterns | tags: react, frontend, javascript
```

Rules:
- `memory/MEMO.md` only references **direct children** (files or folders) — never files inside subfolders.
- Subfolder `MEMO.md` files only reference files within that subfolder.
- Descriptions must be specific enough to determine relevance without opening the file. Max 120 characters.
- Tags must reflect the key concepts — use them for technology names, domains, and categories.
- Tags are the primary matching signal. Descriptions provide context. Both should be useful independently.

---

## Knowledge File Format

Every knowledge file must begin with a frontmatter header:

```markdown
---
created: YYYY-MM-DD
updated: YYYY-MM-DD
---

# Title
```

- `created`: date the file was first written.
- `updated`: date of the last meaningful change. Update this every time the file is modified.
- Never delete the frontmatter. Never leave dates blank.

---

## File Size Guidelines

| Scenario | Action |
|---|---|
| Topic fits in ≤ 150 lines | Single file in `/memory` |
| Topic grows beyond ~150 lines | Split into subfolder with multiple files |
| Subtopic within a folder exceeds ~150 lines | Split into its own file within the subfolder |

The goal is that reading any single file costs fewer than ~150 lines of tokens.

---

## Knowledge Quality Rules

- Write knowledge as **conclusions**, not raw conversation. Skip the back-and-forth, keep the result.
- Include context when it matters (e.g., why a decision was made, what constraint drove it).
- If the same topic is touched across multiple sessions, merge — do not create duplicate files.
- Do not save trivial, easily searchable facts. Save things that required reasoning, experimentation, or user-specific context.
- **Mandatory Final Check**: Every task involving code changes or architectural decisions MUST end with a search for its corresponding memory entry, even if it's to confirm no update is needed.
- **Justification**: If you decide NOT to update the memory after a P1/P2 task, you must explicitly explain why in your final response to the user.

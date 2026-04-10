# Task Decomposition: [SPEC TITLE]

**Spec:** `/specs/YYYY-MM-DD-short-name.md`
**Branch:** `feature/short-name`
**Date:** YYYY-MM-DD

---

## Pre-Flight

- [ ] Create branch: `git checkout -b feature/short-name`
- [ ] Read the spec fully before starting
- [ ] Verify no uncommitted changes on `main`

## Tasks

### Task 1 — [Short description]

**Goal:** _One sentence describing the deliverable_

**Files:**
- `path/to/file.php` — _what to do_

**Steps:**
1. _Concrete step_
2. _Concrete step_
3. _Concrete step_

**Verify:** _How to confirm this task is done (curl command, browser check, SQL query, etc.)_

---

### Task 2 — [Short description]

**Goal:** _One sentence_

**Files:**
- `path/to/file.php` — _what to do_

**Steps:**
1. _Concrete step_
2. _Concrete step_

**Verify:** _How to confirm_

---

### Task 3 — [Short description]

_Repeat the pattern. Each task should be independently committable._

---

## Post-Flight

- [ ] Run full smoke test (health check, widget load, chat round-trip)
- [ ] `git diff main` — review all changes
- [ ] Commit with descriptive message: `feat: [short-name] — [what changed]`
- [ ] Push branch: `git push -u origin feature/short-name`
- [ ] Open PR (or merge to main if solo)
- [ ] Deploy to VPS and verify in production

## Notes

_Anything discovered during implementation — gotchas, decisions made, things deferred._

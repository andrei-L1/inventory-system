# Git Multi-Device Workflow Guide

This guide outlines the standard operating procedure for developers working on the **Inventory System** across multiple machines (e.g., Desktop and Laptop). Following these steps will prevent merge conflicts and ensure data integrity across your local environments.

---

## The "Golden Triangle" Rule: **Pull - Work - Push**

Every development session should follow this exact sequence to keep your devices in sync via the remote repository.

### 1. START: Sync Your Local Workspace
Before writing any code or starting a feature, ensure your local branch is up to date.
```bash
# Update information from the remote
git fetch origin

# Incorporate changes into your current branch
git pull origin [your-branch-name]
```
> [!IMPORTANT]
> Always `git pull` before your first file edit of the day.

### 2. DURING: Commit Logically
Group your work into small, logical commits. This makes it easier to track progress and revert if necessary.
```bash
git add .
git commit -m "feat: implement logic for X"
```

### 3. END: Push to Remote
Before switching to another device or ending your session, ensure your work is uploaded to the cloud.
```bash
git push origin [your-branch-name]
```
> [!TIP]
> Even if a feature is incomplete, consider a "WIP" (Work In Progress) commit so you can pick it up on your other device.

---

## Best Practices for Conflict Prevention

### 1. Use Feature Branches
Avoid working on the `main` or `master` branch directly. Create short-lived branches for specific tasks:
```bash
git checkout -b feature/issue-123-ui-fix
```

### 2. Communicate with Yourself (WIP)
If you need to leave and continue on another device but the code isn't "perfect":
1. `git commit -m "wip: partial progress on UI"`
2. `git push origin [branch]`
3. On the **second device**: `git pull origin [branch]`
4. Once finished: use `git commit --amend` or a squash to clean the history.

### 3. Handling a "Blocked" Push
If you try to `git push` and get an error (e.g., "non-fast-forward"), your other device likely pushed changes you haven't pulled yet.
- **DO NOT** use `--force` unless you are absolutely sure.
- **DO** `git pull` to merge the changes, resolve any conflicts locally, then push.

### 4. Verification Check
Running `git status` frequently is the best way to avoid surprises:
- If it says `Your branch is behind 'origin/main'`, **pull immediately**.
- If it says `Your branch is ahead of 'origin/main'`, **push before leaving**.

---

## Standard Conflict Resolution Pattern
If a conflict occurs during a pull:
1. Open the file with conflicts.
2. Look for `<<<<<<< HEAD`, `=======`, and `>>>>>>> [commit-hash]`.
3. Choose the code you want to keep.
4. Remove the Git markers.
5. Save the file.
6. Run:
   ```bash
   git add [conflicted-file]
   git commit -m "merge: resolve conflicts between desktop and laptop"
   git push
   ```

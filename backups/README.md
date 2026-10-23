# Continuous Backups — Zolo SmartSchool

This directory holds continuous local backups for the Zolo SmartSchool platform.

## Directory Structure
- `snapshots/` — Compressed `.zip` archives of the codebase and assets (excluding vendor/cache dependencies).
- `git_bundles/` — Offline `.bundle` files containing complete Git revision history, branches, and tags.
- `backup_log.json` — Structured JSON ledger of all completed backups with timestamps, commit SHAs, and sizes.

## How to Trigger a Backup

Run the automated backup script from the workspace root:
```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup_project.ps1 -Description "Your backup note here"
```

### Options:
- `-SkipZip` — Only create a Git bundle (instant).
- `-SkipBundle` — Only create a `.zip` snapshot of files.
- `-Description "<text>"` — Note describing what was completed before this backup.

## How to Restore

### From a Git Bundle:
```bash
git clone backups/git_bundles/zolo_git_<timestamp>_<commit>.bundle restored_project
```
Or pull refs into an existing clone:
```bash
git fetch backups/git_bundles/zolo_git_<timestamp>_<commit>.bundle main:restored_main
```

### From a Snapshot Zip:
Extract the `.zip` archive from `backups/snapshots/` to your target directory, then run:
```bash
composer install
npm install
php artisan migrate
```

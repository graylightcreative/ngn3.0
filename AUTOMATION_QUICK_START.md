# NGN Automation - Quick Start Guide

Get started with automated deployments in 2 minutes.

## Installation

✅ **Already installed!** No setup required. All files are in place.

## First Time: Preview Mode

Always preview first with dry-run mode:

```bash
php bin/automate.php full --version=2.0.3 --dry-run
```

This shows you exactly what will happen without making any changes.

**Sample output:**
```
DRY-RUN MODE - No changes will be made

Full Automation Workflow
Version: 2.0.3
DRY-RUN: No changes will be committed or deployed

Progress:
✓ Starting full automation workflow
✓ State validated
✓ (DRY-RUN) Would update progress files
✓ (DRY-RUN) Would update README.md
✓ (DRY-RUN) Would create commit: "NGN 2.0.3: ..."
✓ (DRY-RUN) Would push to origin/main
✓ (DRY-RUN) Would deploy via nexus fleet-deploy

DRY-RUN COMPLETE - No changes made
```

## Execute: Real Deployment

Once you're confident, run for real:

```bash
php bin/automate.php full --version=2.0.3
```

This will:
1. ✓ Update progress files
2. ✓ Regenerate README with status
3. ✓ Create git commit
4. ✓ Push to origin/main
5. ✓ Deploy via Fleet

## Available Commands

### Full Workflow (Recommended)
```bash
php bin/automate.php full --version=2.0.3
```

### Individual Operations
```bash
php bin/automate.php progress --version=2.0.3   # Update progress
php bin/automate.php readme --version=2.0.3     # Update README
php bin/automate.php commit --version=2.0.3     # Commit & push
php bin/automate.php deploy --version=2.0.3     # Deploy only
```

### Utilities
```bash
php bin/automate.php status       # Show current status
php bin/automate.php help         # Show help
php bin/automate.php rollback --to=79d5e67  # Rollback to commit
```

## Common Scenarios

### Scenario 1: Deploy new features

```bash
# 1. Implement features (code already in repo)
# 2. Preview changes
php bin/automate.php full --version=2.0.4 --dry-run

# 3. Execute if preview looks good
php bin/automate.php full --version=2.0.4

# Done! Progress updated, README updated, deployed.
```

### Scenario 2: Just update progress (no deployment)

```bash
php bin/automate.php progress --version=2.0.3
```

### Scenario 3: Update README status

```bash
php bin/automate.php readme --version=2.0.3
```

### Scenario 4: Rollback if something went wrong

```bash
# Find the commit hash
git log --oneline -5

# Rollback to a previous commit
php bin/automate.php rollback --to=79d5e67
```

## What Gets Automated

✅ **Progress Tracking**
- Reads `storage/plan/progress-beta-X.X.X.json`
- Auto-detects completed tasks (by checking for required files)
- Updates completion percentage
- Saves changes

✅ **README Updates**
- Generates status banner with progress bar
- Lists completed/pending tasks
- Updates last-updated timestamp
- Preserves your manual content

✅ **Git Operations**
- Stages progress files + README
- Creates structured commit message
- Includes task summaries
- Pushes to origin/main

✅ **Deployment**
- Validates prerequisites (nexus CLI, credentials)
- Executes Fleet deployment
- Records deployment in audit log
- Shows deployment report

## Troubleshooting

### "nexus command not found"
Install Graylight Fleet CLI (see FLEET.md)

### "Uncommitted changes detected"
Commit your changes first:
```bash
git add .
git commit -m "Your message"
```

### ".env file not found"
Create from template:
```bash
cp .env.example .env
# Fill in your credentials
```

### Deployment fails
Check prerequisites:
```bash
php bin/automate.php status
```

## Tips & Best Practices

1. **Always use --dry-run first**
   ```bash
   php bin/automate.php full --version=X.X.X --dry-run
   ```

2. **Review what will be staged**
   ```bash
   git status
   ```

3. **Test on beta first**
   - Deploy to beta environment
   - Verify on beta.nextgennoise.com
   - Then deploy to production

4. **Keep progress files in sync**
   - Auto-detection updates most tasks
   - Manual updates only when needed

5. **Document deployments**
   - Add notes to README changes
   - Include in commit messages

## Performance

- **Dry-run**: ~2 seconds
- **Full workflow**: ~3-5 seconds
- **Individual commands**: ~1-2 seconds

## Need More Help?

- See full guide: `docs/AUTOMATION_GUIDE.md`
- Show help: `php bin/automate.php help`
- Check status: `php bin/automate.php status`

## Summary

That's it! You now have:

✅ Automated progress tracking
✅ Auto-generated README status
✅ Structured git commits
✅ One-command deployments
✅ Complete audit logging

**Reduce 30-minute deployments to 2-minute automation.** 🚀

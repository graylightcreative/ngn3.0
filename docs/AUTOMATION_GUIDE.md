# NGN Automation Master Script - User Guide

The NGN Automation Master Script streamlines the deployment workflow by orchestrating progress tracking, git operations, and remote deployments in a single command.

## Overview

The automation system replaces manual 30+ minute workflows with a 2-minute automated process. It handles:

1. **Progress Tracking** - Auto-detect completed tasks and update progress files
2. **Documentation** - Regenerate README with dynamic status banners
3. **Git Operations** - Stage files, create structured commits, push to remote
4. **Deployment** - Execute Fleet deployments with audit logging

## Quick Start

### Full Workflow (Recommended)

```bash
# Preview changes without executing
php bin/automate.php full --version=2.0.3 --dry-run

# Execute full workflow
php bin/automate.php full --version=2.0.3
```

The full workflow will:
1. ✓ Validate system state
2. ✓ Update progress tracking
3. ✓ Regenerate README
4. ✓ Create git commit
5. ✓ Push to origin/main
6. ✓ Deploy via Fleet

### Individual Commands

```bash
# Update progress only
php bin/automate.php progress --version=2.0.3

# Regenerate README
php bin/automate.php readme --version=2.0.3

# Create and push commit
php bin/automate.php commit --version=2.0.3

# Deploy via Fleet
php bin/automate.php deploy --version=2.0.3

# Show current status
php bin/automate.php status

# Show help
php bin/automate.php help
```

## Features

### 1. Progress Tracking

The script automatically:
- Reads `progress-beta-X.X.X.json` files
- Auto-detects completed tasks by checking for required files
- Recalculates completion percentages
- Updates master `progress.json`

**File-based Detection:**
```json
{
  "blockchain_anchoring": [
    "lib/Blockchain/BlockchainService.php",
    "lib/Blockchain/SmartContractInterface.php"
  ]
}
```

When both files exist, the task is automatically marked complete.

### 2. Dynamic README Updates

The script generates status banners with:
- Current completion percentage
- Visual progress bar
- Task summary (completed, in-progress, pending)
- Last updated timestamp

Example output:
```
<!-- AUTO-GENERATED STATUS BANNER - v2.0.3 -->
## Status: ⚙️ ACTIVE_DEVELOPMENT

**Progress:** 3/12 tasks completed (25%)

█████░░░░░░░░░░░░░░ 25%

**Latest Update:** 2026-02-13T14:30:00Z
```

### 3. Structured Git Commits

Auto-generates commit messages with:
- Version and summary
- List of completed tasks
- File count
- Completion percentage

Example:
```
NGN 2.0.3: Mint ERC-721 NFT certificates

Completed tasks:
- Mint ERC-721 NFT certificates
- Create admin dashboard for ledger management

Files changed: 3
Completion: NGN 2.0.3
```

### 4. Fleet Deployment Integration

The script:
- Validates prerequisites (nexus CLI, credentials)
- Executes `nexus fleet-deploy`
- Records deployment in audit log
- Provides deployment history

## Configuration

**Location:** `config/automation.json`

Key settings:
- `git.auto_push` - Automatically push after commit (default: true)
- `deployment.default_environment` - Target environment (default: beta)
- `logging.enabled` - Enable deployment logging (default: true)
- `safety_checks.*` - Validation requirements

## Safety Features

### Dry-Run Mode

Always use `--dry-run` to preview changes:
```bash
php bin/automate.php full --version=2.0.3 --dry-run
```

Output shows exactly what would happen without making changes.

### Pre-Flight Checks

Validates:
- ✓ Git is on main branch
- ✓ Working directory is clean (warning only)
- ✓ .env file exists
- ✓ Credentials are configured
- ✓ nexus CLI is available

### Rollback

Rollback to previous commit:
```bash
php bin/automate.php rollback --to=79d5e67

# System will prompt for confirmation
Rolling back to: 79d5e67
This operation cannot be undone. Continue? (yes/no): yes
```

## Workflow Examples

### Scenario 1: Simple Progress Update

```bash
# You completed a feature
# 1. Files are in the repo
# 2. Want to update progress

php bin/automate.php progress --version=2.0.3
```

### Scenario 2: Full Release Deployment

```bash
# 1. Code is complete
# 2. Need to update progress, docs, commit, and deploy

# Preview first
php bin/automate.php full --version=2.0.4 --dry-run

# Then execute
php bin/automate.php full --version=2.0.4
```

### Scenario 3: Emergency Rollback

```bash
# Something went wrong with last deployment
git log --oneline  # Find good commit hash

# Rollback
php bin/automate.php rollback --to=abc1234
```

## Logging

All operations are logged to:
- **Console Output** - Real-time colored status
- **Audit Log** - `storage/logs/automation/deploy-history.json`
- **Activity Log** - `storage/logs/automation/automation-YYYY-MM-DD.log`

View deployment history:
```bash
cat storage/logs/automation/deploy-history.json
```

## Troubleshooting

### "nexus command not found"

Install Graylight Fleet CLI:
```bash
# Follow FLEET.md installation instructions
```

### "Uncommitted changes in git"

Commit or stash changes:
```bash
git status
git add .
git commit -m "Your changes"
```

### ".env file not found"

Copy from template:
```bash
cp .env.example .env
# Fill in credentials
```

### Deployment fails with "Prerequisites not met"

Run pre-flight check:
```bash
php bin/automate.php status
```

Address any warnings shown.

## Architecture

### Service Classes

**AutomationService** - Main orchestrator
- Coordinates all steps
- Manages workflow execution
- Generates status reports

**ProgressTracker** - Progress file management
- Reads/writes JSON files
- Auto-detects completions
- Calculates metrics

**ReadmeGenerator** - Documentation updates
- Generates status banners
- Parses existing README
- Preserves manual sections

**GitCommitter** - Git operations
- Stages files
- Creates commits
- Pushes to remote

**FleetDeployer** - Fleet integration
- Wraps nexus CLI
- Validates prerequisites
- Maintains audit log

### File Structure

```
bin/
  └── automate.php              # CLI entry point
lib/Automation/
  ├── AutomationService.php     # Main orchestrator
  ├── ProgressTracker.php       # Progress file management
  ├── ReadmeGenerator.php       # README updates
  ├── GitCommitter.php          # Git operations
  └── FleetDeployer.php         # Fleet integration
config/
  └── automation.json           # Configuration
storage/logs/automation/
  ├── automation-*.log          # Activity logs
  └── deploy-history.json       # Deployment audit trail
```

## Best Practices

1. **Always use --dry-run first**
   ```bash
   php bin/automate.php full --version=X.X.X --dry-run
   ```

2. **Review changes before committing**
   ```bash
   git diff
   git status
   ```

3. **Test on beta before production**
   ```bash
   # Deploy to beta first
   php bin/automate.php deploy

   # Verify on beta.nextgennoise.com
   # Then deploy to production
   ```

4. **Keep progress.json in sync**
   - Update task status manually only in special cases
   - Let auto-detection handle most updates

5. **Document deployments**
   - Add notes to DEPLOYMENT_NOTES.md
   - Include in commit messages

## Performance

- **Dry-run**: ~2 seconds
- **Full workflow**: ~3-5 seconds (excludes network/deployment time)
- **Individual commands**: ~1-2 seconds

## Support

For issues or questions:
1. Check `php bin/automate.php help`
2. Review `config/automation.json`
3. Check logs: `storage/logs/automation/`
4. See [TROUBLESHOOTING_GUIDE.md](./TROUBLESHOOTING_GUIDE.md)

## Version History

**v1.0** (2026-02-13)
- Initial release
- Full workflow automation
- Progress tracking with auto-detection
- Structured git commits
- Fleet deployment integration
- Comprehensive logging and safety checks

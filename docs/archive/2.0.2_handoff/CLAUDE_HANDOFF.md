# Claude Instance Handoff - NGN 2.0.2 Development

## Welcome to ngn_202 (2.0.2 Development Environment)

You are now in the **official 2.0.2 development directory**. This is your workspace for building new features and enhancements.

## Current Status

✅ **Ready for Development**
- Branch: `main` (has all 2.0.1 fixes merged, no regressions)
- Version: NGN 2.0.2
- Environment: Development
- Storage: Configured (.env + /storage present)
- Dependencies: Installed (composer done)

## What's Already Complete

### 2.0.1 (PRODUCTION - DO NOT TOUCH)
- ✅ Email capture popup (all public pages)
- ✅ Mailchimp integration
- ✅ localStorage persistence (7-day dismissal)
- ✅ Deployed to nextgennoise.com and beta.nextgennoise.com
- ✅ Live and testing in production
- **Location**: `/Users/brock/Documents/Projects/ngn_201` (2.0.1-stable branch)

### 2.0.2 (COMPLETED FEATURES - YOU CAN BUILD ON)
- ✅ Infrastructure reorganization (version banners, subdomain routing, health checks)
- ✅ Digital Safety Seal (content ledger, certificates, verification API)
- ✅ Custom domain verification framework
- ✅ Deployment & rollback procedures documented
- ✅ Email capture popup (inherited from 2.0.1)

## Project Structure

```
ngn_202/ (THIS DIRECTORY)
├── .env                 (Database + Mailchimp credentials)
├── storage/             (Uploads, logs, certificates, etc.)
├── lib/
│   ├── bootstrap.php
│   ├── partials/
│   │   ├── global-footer.php        (Email capture popup)
│   │   ├── header.php
│   │   └── footer.php
│   ├── handlers/
│   │   └── newsletter-signup.php    (Mailchimp integration)
│   ├── Legal/
│   │   ├── ContentLedgerService.php
│   │   └── DigitalCertificateService.php
│   └── ...
├── public/
│   ├── index.php        (Main app - includes global footer)
│   ├── artists.php
│   ├── labels.php
│   ├── stations.php
│   ├── charts.php
│   └── ... (all have global footer)
├── vendor/              (Composer dependencies)
└── composer.json
```

## How to Start Development

### 1. Review Progress Files
- **MEMORY.md** (in `.claude/projects/.../memory/`) - Quick summary of completed work
- **STATUS.md** (in current directory) - Current state and deployment checklist

### 2. Start Local Testing
```bash
cd /Users/brock/Documents/Projects/ngn_202
php -S localhost:8000
# Navigate to http://localhost:8000/public/index.php
# You should see email capture popup after 2 seconds
```

### 3. Key Git Commands
```bash
# Check current branch
git branch -v
# Should be: * main  a80be08 fix: Add email capture popup...

# View commit history
git log --oneline -5

# Create feature branch for new work
git checkout -b feature/your-feature-name

# Commit changes
git add .
git commit -m "feat: Your feature description"

# Push to develop on your feature branch
git push origin feature/your-feature-name
```

## Important Notes

### ✅ DO THIS
- Develop new 2.0.2 features in this directory
- Create feature branches for new work
- Test locally before committing
- Reference MEMORY.md and STATUS.md for context
- Commit frequently with clear messages
- Keep 2.0.1 (ngn_201) UNTOUCHED unless critical fix needed

### ❌ DON'T DO THIS
- **Never modify /Users/brock/Documents/Projects/ngn_201** unless explicitly told
- Don't merge changes from 2.0.1 back to 2.0.2 (already done at commit a80be08)
- Don't change version numbers without guidance
- Don't modify .env without understanding implications
- Don't deploy to production from this directory

## 2.0.2 Development Focus

Based on MEMORY.md, the following are ready for implementation:
- [ ] Define 2.0.2 specific features (new features beyond 2.0.1)
- [ ] Build on existing infrastructure (subdomains, health checks)
- [ ] Enhance Digital Safety Seal (blockchain anchoring, NFT minting - future)
- [ ] Add new payment/commerce features
- [ ] Implement user dashboard enhancements
- [ ] Expand artist/label profile functionality

## Emergency Reference

**If you need to understand the full context:**
1. Read `MEMORY.md` (quick overview)
2. Read `STATUS.md` (current deployment state)
3. Check git log (commit history)
4. Read `docs/INFRASTRUCTURE_DEPLOYMENT_GUIDE.md` (deployment procedures)
5. Read `docs/DEPLOYMENT_ROLLBACK_PROCEDURES.md` (if something breaks)

## Quick Links

- 2.0.1 Production: https://nextgennoise.com
- 2.0.1 Beta: https://beta.nextgennoise.com
- Local Dev: http://localhost:8000/public/index.php
- Git Repo: https://github.com/graylightcreative/ngn2.0

## Database & API

**Database**: ngn_2025 (MySQL on server.starrship1.com)
- Read-only API key in .env as DB_USER/DB_PASS
- Connection established via bootstrap.php

**Mailchimp**:
- API Key in .env: MAILCHIMP_API_KEY
- Audience ID: MAILCHIMP_AUDIENCE_ID
- Auto-integrates with newsletter signup

**Email Handler**: /lib/handlers/newsletter-signup.php
- POST endpoint for form submissions
- Auto-saves to Contacts table
- Auto-adds to Mailchimp list
- Sends welcome emails

---

**Last Handoff**: 2026-02-07
**Previous Instance**: Completed 2.0.1 email capture popup deployment and setup
**Status**: ✅ READY FOR 2.0.2 FEATURE DEVELOPMENT

**Happy coding! 🚀**

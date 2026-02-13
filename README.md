# NGN 2.0 Project Documentation

<!-- AUTO-GENERATED STATUS BANNER - v2.0.3 -->
## Status: 🔧 EARLY_DEVELOPMENT

**Progress:** 2/12 tasks completed (16%)

```
████░░░░░░░░░░░░░░░░░█ 16%
```

**Latest Update:** 2026-02-13T18:13:26Z

<!-- END AUTO-GENERATED BANNER -->


This README serves as a central index for all documentation related to the NGN 2.0 project. It provides quick access to essential guides, plans, and technical references.

---

## 🚀 BETA 2.0.1 - READY FOR LAUNCH

**Status**: ✅ READY | **33 Bible Chapters**: ✅ IMPLEMENTED | **47 Migrations**: ✅ TESTED | **All Systems**: GO

### Quick Launch
- **[📖 Beta 2.0.1 Launch Guide](./BETA_2.0.1_LAUNCH.md)** ← START HERE for deployment instructions
- **[🎯 Beta 2.0.1 Roadmap](./BETA_2.0.1_ROADMAP.md)** - Complete feature list and status
- **[🔧 Migration System](./docs/migrations/README.md)** - 47 active migrations, organized and tested
- **[✅ What's Included](#-beta-201-features)** - Full feature breakdown

---

## 💡 Beta 2.0.1 Features

**Foundation (Tier 1)**: Core data model, ranking engine, API v1, operations manual
**Content & Ecosystem (Tier 2)**: Products, tickets, writer engine, touring spec
**Infrastructure (Tier 3)**: Multi-platform, security, royalty system, rights ledger
**Integrity & Growth (Tier 4)**: Transparency, discovery, social feed, retention
**Governance (Tier 5)**: ✨ NEW Directorate SIR system, board structure, equity phases

**Full Details**: See [BETA_2.0.1_ROADMAP.md](./BETA_2.0.1_ROADMAP.md) and [docs/bible/](./docs/bible/)

---

## Quick Reference

- [Quick Reference Guide](./QUICK_REFERENCE.md)
- [Documentation Index](./DOCUMENTATION_INDEX.md)
- [Command Reference](./COMMAND_REFERENCE.sh)

---

## 🏛️ Core Architecture & Strategy

High-level documentation covering the project's vision, architecture, and strategic plans.

- **Foundational Guides**
    - [Project Bible Index](./docs/bible/00%20-%20Bible%20Index.md)
    - [Public Structure Guide](./PUBLIC_STRUCTURE_GUIDE.md)
    - [Legacy Database Schema](./docs/LegacyDatabaseSchema.md)
- **Technical Systems**
    - [Discovery Engine](./docs/DISCOVERY_ENGINE.md)
    - [Engagement System](./docs/ENGAGEMENT_SYSTEM.md)
    - [Video System](./docs/VIDEO_SYSTEM.md)
    - [Social Feed Implementation](./SOCIAL_FEED_IMPLEMENTATION.md)
- **Strategy & Planning**
    - [Monetization Plan 2025](./MONETIZATION_PLAN_2025.md)
    - [API Investments](./docs/API_INVESTMENTS.md)
    - [Roadmap](./docs/roadmap/ROADMAP.md)

---

## 🚀 Deployment & Operations

Checklists, runbooks, and guides for deploying and managing the NGN 2.0 application.

- **Master Checklists**
    - [Deployment Checklist](./DEPLOYMENT_CHECKLIST.md)
    - [Go-Live Checklist](./docs/GO_LIVE_CHECKLIST.md)
    - [Launch Day Master Checklist](./LAUNCH_DAY_MASTER_CHECKLIST.md)
- **Automation & Deployment**
    - [🤖 Automation Guide](./docs/AUTOMATION_GUIDE.md) ← **NEW: Orchestrate deployments automatically**
    - [Automation Quick Reference](#-automation-quick-reference) ← **Quick command reference**
- **Runbooks & Guides**
    - [Migration Plan](./MIGRATION_PLAN.md)
    - [Cutover Runbook](./docs/CutoverRunbook.md)
    - [GA Cutover Deployment](./docs/GA_CUTOVER_DEPLOYMENT.md)
    - [AAPANEL Cron Setup Guide](./AAPANEL_CRON_SETUP_GUIDE.md)
    - [Cron Settings](./docs/CronSettings.md)
    - [Troubleshooting Guide](./TROUBLESHOOTING_GUIDE.md)

---

## 🧪 Beta & Testing

Documentation for the beta program, testing plans, and quality assurance.

- **Beta Program**
    - [Beta 2.0.1 Roadmap](./BETA_2.0.1_ROADMAP.md)
    - [Beta Tester Onboarding Guide](./BETA_TESTER_ONBOARDING_GUIDE.md)
- **Testing Strategy**
    - [Stripe Testing Guide](./docs/STRIPE_TESTING_GUIDE.md)
    - [Testing Plan for Writer Engine](./TESTING_PLAN_WRITER_ENGINE.md)
    - [Testing Tracker README](./TESTING_TRACKER_README.md)
- **Acceptance & Audits**
    - [Acceptance Criteria](./docs/Acceptance.md)
    - [NGN Score Audit](./docs/NGN_SCORE_AUDIT.md)

---

## 📊 Reports & Summaries

Dashboards, reports, and summaries that provide insight into project progress and performance.

- [Beta Progress Dashboard](./BETA_PROGRESS_DASHBOARD.md)
- [Implementation Summary](./IMPLEMENTATION_SUMMARY.md)
- [Governance Implementation Summary](./GOVERNANCE_IMPLEMENTATION_SUMMARY.md)
- [Monitoring and Alerts](./MONITORING_AND_ALERTS.md)
- [P95 Latency Monitoring](./docs/P95_LATENCY_MONITORING.md)
- [Post-Launch Analytics](./docs/POST_ANALYTICS.md)

---

## 👥 User Stories

- [Artists](./docs/user_stories/artists.md)
- [Fans](./docs/user_stories/fans.md)
- [Labels](./docs/user_stories/labels.md)
- [Stations](./docs/user_stories/stations.md)
- [Venues](./docs/user_stories/venues.md)

---

## 🎵 Streaming Platform (PHASE 1 - LIVE)

**Status**: ✅ PHASE 1 COMPLETE | **Database**: ✅ MIGRATED | **API**: ✅ OPERATIONAL

The NGN 2.0 music streaming platform with secure audio delivery and integrated royalty tracking.

### Phase 1: Audio Infrastructure & Streaming API
- **[README_PHASE1.md](./README_PHASE1.md)** - Quick reference & testing guide
- **[PHASE1_QUICKSTART.md](./PHASE1_QUICKSTART.md)** - 30-minute setup guide with curl examples
- **[PHASE1_IMPLEMENTATION.md](./PHASE1_IMPLEMENTATION.md)** - Complete architecture & API documentation
- **[PHASE1_STATUS.md](./PHASE1_STATUS.md)** - Deployment instructions & checklist
- **[IMPLEMENTATION_PROGRESS.md](./IMPLEMENTATION_PROGRESS.md)** - Full 8-week timeline (Phases 1-5)

### Core Features
✅ Secure streaming with signed URLs (SHA-256 tokens, 15-min expiry)
✅ One-time use token enforcement (replay protection)
✅ HTTP 206 Range request support (seekable audio)
✅ Rights ledger integration (respects existing royalty system)
✅ Full audit logging (session, IP, user-agent tracking)
✅ Playback event logging (ready for royalty triggers)

### API Endpoints
```
GET /api/v1/tracks/{id}/token        - Generate streaming token
GET /api/v1/tracks/{id}/stream?token - Stream audio with Range support
```

### Implementation Files
- **Service**: `lib/Services/Media/StreamingService.php` (token generation, streaming, validation)
- **Database**: `migrations/active/infrastructure/040_audio_storage.sql` (schema enhancements)
- **Storage**: `storage/audio/` (tracks, BYOS, temp directories)
- **API**: `public/api/v1/index.php` (token & stream endpoints)

### Upcoming Phases
- **Phase 2**: Modern player with queue management (ES6 class, Media Session API)
- **Phase 3**: Playback tracking & royalty triggers (qualified listens, cron jobs)
- **Phase 4**: Station radio streaming (playlist API, BYOS mixing)
- **Phase 5**: Service Worker & background audio (offline queue, lock screen controls)

---

## ⚙️ Technical References

Deeper technical documentation and module-specific READMEs.

- **API & Governance**
    - [API Reference - Governance](./API_REFERENCE_GOVERNANCE.md)
    - [Fairness Doctrine](./docs/Fairness.md)
    - [Scoring Explained](./docs/Scoring.md)
- **Streaming**
    - [StreamingService Documentation](./README_PHASE1.md)
    - [API Endpoints Reference](./PHASE1_IMPLEMENTATION.md#-api-endpoints)
- **Module Documentation**
    - [Jobs README](./jobs/README.md)
    - [Library README](./lib/README.md)
    - [Admin Panel README](./public/admin/README.md)
    - [Public API README](./public/api/README.md)
    - [Tests README](./tests/README.md)

---

## 🤖 Automation Quick Reference

**Automate progress tracking, git commits, and deployments in one command**

### Basic Usage

```bash
# Preview changes (no modifications)
php bin/automate.php full --version=2.0.3 --dry-run

# Execute full workflow
php bin/automate.php full --version=2.0.3

# Individual operations
php bin/automate.php progress --version=2.0.3    # Update progress
php bin/automate.php readme --version=2.0.3      # Regenerate README
php bin/automate.php commit --version=2.0.3      # Create commit & push
php bin/automate.php deploy --version=2.0.3      # Deploy via Fleet
php bin/automate.php status                      # Show current status
```

### Workflow Steps

The `full` command executes these steps automatically:

1. **Validate State** - Check git, credentials, files
2. **Update Progress** - Auto-detect completed tasks, update metrics
3. **Regenerate README** - Update status banners with latest progress
4. **Create Commit** - Stage files, generate structured commit message
5. **Push to Remote** - Push to origin/main
6. **Deploy** - Execute Graylight Fleet deployment

### Features

- ✅ **Auto-detection** - Automatically mark tasks complete based on file existence
- ✅ **Dry-run mode** - Preview all changes without executing
- ✅ **Structured commits** - Auto-generated messages with task summaries
- ✅ **Audit logging** - Complete deployment history
- ✅ **Safety checks** - Validates prerequisites before operations
- ✅ **Rollback** - Rollback to previous commits

### Configuration

- **Settings**: `config/automation.json`
- **Logs**: `storage/logs/automation/`
- **History**: `storage/logs/automation/deploy-history.json`

**Full Guide**: [📖 Automation Guide](./docs/AUTOMATION_GUIDE.md)
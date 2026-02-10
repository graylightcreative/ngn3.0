# Admin Panel Migration Checklist

## Pre-Migration (Before Week 5)

### Database Setup
- [ ] Create all tables from `DATABASE_SCHEMA_ADMIN_V2.md`
  - [ ] smr_ingestions, smr_records
  - [ ] cdm_rights_ledger, cdm_rights_splits, cdm_rights_disputes
  - [ ] cdm_identity_map
  - [ ] cdm_chart_entries
- [ ] Create indexes for performance
- [ ] Verify foreign key relationships
- [ ] Backup existing database

### Environment Setup
- [ ] Node.js 18+ installed
- [ ] `npm install` completed in `/public/admin-v2/`
- [ ] `npm run build` successful (creates `dist/` folder)
- [ ] Verify `dist/` is in `.gitignore`

### Authentication
- [ ] Test JWT validation via `/login.php`
- [ ] Verify `_guard.php` works with Bearer tokens
- [ ] Test `/admin-v2/index.php` loads React app
- [ ] Verify JWT passed to React via `window.NGN_ADMIN_TOKEN`
- [ ] Test 401 redirect works

## Phase 3: Royalties (Week 5-6)

### Backend
- [ ] Create RoyaltyService.php
  - [ ] `getBalance()` - User's current balance
  - [ ] `getPendingPayouts()` - Queue
  - [ ] `getTransactions()` - Ledger
  - [ ] `calculateEQS()` - EQS formula
  - [ ] `createPayout()` - Submit payout
  - [ ] `verifyStripeConnect()` - Check integration
- [ ] Add 6+ endpoints to admin_routes.php
- [ ] Test EQS calculation logic

### Frontend
- [ ] Build RoyaltiesDashboard.tsx
  - [ ] Summary stats (pool, pending, Stripe status)
  - [ ] EQS breakdown visualization
  - [ ] Recent payouts list
- [ ] Build RoyaltiesPayouts.tsx
  - [ ] Pending payout queue (filterable)
  - [ ] Process payout form
  - [ ] Transaction history
- [ ] Create royaltyService.ts with API client
- [ ] Create Royalty.ts types

### Testing
- [ ] Test EQS calculation matches Bible formula
- [ ] Test payout creation and status tracking
- [ ] Test Stripe Connect monitoring
- [ ] Verify transaction ledger updates
- [ ] Test error handling (failed payouts)

## Phase 4: Chart QA (Week 6-7)

### Backend
- [ ] Create ChartQAService.php
  - [ ] `getQAStatus()` - Gate validation
  - [ ] `getCorrections()` - Manual corrections
  - [ ] `applyCorrection()` - Save correction
  - [ ] `getDisputes()` - Dispute queue
  - [ ] `resolveDispute()` - Mark resolved
  - [ ] `approvePublish()` - Finalize chart
- [ ] Add 5+ endpoints to admin_routes.php
- [ ] Implement QA validation gates

### Frontend
- [ ] Build ChartQAGatekeeper.tsx
  - [ ] QA status dashboard (4 gates)
  - [ ] Gate details and status
  - [ ] Issues list with severity
- [ ] Build ChartCorrections.tsx
  - [ ] Correction form
  - [ ] Bulk corrections
  - [ ] History of corrections
- [ ] Build ChartDisputes.tsx
  - [ ] Open disputes list
  - [ ] Resolution form
  - [ ] Resolved disputes
- [ ] Create chartService.ts with API client
- [ ] Create Chart.ts types

### Testing
- [ ] Test all 4 QA gates calculate correctly
- [ ] Test manual correction workflow
- [ ] Test dispute resolution
- [ ] Verify chart publish approval works
- [ ] Test chart history/rollback

## Phase 5: Expansion (Week 7-8)

### Entity Management
- [ ] Build EntitiesArtists.tsx
  - [ ] CRUD operations
  - [ ] Verification status toggle
  - [ ] Identity mapping UI
  - [ ] Merge duplicates dialog
- [ ] Build EntitiesLabels.tsx, EntitiesStations.tsx, EntitiesVenues.tsx
- [ ] Create EntityService with 4 endpoints per type

### Content Moderation
- [ ] Build ModerationReports.tsx
  - [ ] User reports queue
  - [ ] Report details and investigation
  - [ ] Resolution form
- [ ] Build ModerationTakedowns.tsx
  - [ ] DMCA request handling
  - [ ] Status tracking
  - [ ] Bulk operations
- [ ] Create ModerationService with endpoints

### System Operations
- [ ] Build SystemCronMonitor.tsx
  - [ ] Cron job status from Bible Ch. 5 registry
  - [ ] Last run times
  - [ ] Error logs
- [ ] Build SystemAPIHealth.tsx
  - [ ] Endpoint health checks
  - [ ] Response times
  - [ ] Error rate monitoring
- [ ] Build SystemFeatureFlags.tsx
  - [ ] List all flags
  - [ ] Toggle flags
  - [ ] Deployment status

### Platform Analytics
- [ ] Build AnalyticsRevenue.tsx
  - [ ] Revenue charts
  - [ ] Payout trends
  - [ ] EQS distribution
- [ ] Build AnalyticsEngagement.tsx
  - [ ] User activity
  - [ ] Platform metrics
  - [ ] Growth tracking

### Testing
- [ ] Full end-to-end workflow for each module
- [ ] API error handling
- [ ] UI/UX on mobile (responsive)
- [ ] Performance: load times <2s
- [ ] Security: auth on all endpoints

## Phase 6: Polish & Cutover (Week 8)

### Comprehensive Testing
- [ ] SMR workflow: Upload → Map → Finalize (end-to-end)
- [ ] Rights: Create → Verify → Dispute → Resolve
- [ ] Royalties: Payout creation → Processing → Completion
- [ ] Chart QA: Validation → Correction → Publish
- [ ] All entity types: CRUD, merge, verify
- [ ] Moderation: Report → Resolve, Takedown → Complete
- [ ] System dashboards functional
- [ ] Analytics data accurate

### Performance Optimization
- [ ] Code split routes for lazy loading
- [ ] Minify CSS/JS in production build
- [ ] Compress images (icons)
- [ ] Implement pagination for large lists
- [ ] Cache frequently accessed data
- [ ] Verify <2s page load time

### Security Audit
- [ ] All endpoints require JWT
- [ ] Role-based access control enforced
- [ ] Input validation on all forms
- [ ] SQL injection protection (parameterized queries)
- [ ] XSS prevention (React escaping)
- [ ] CSRF protection (SameSite cookies)
- [ ] No sensitive data in logs
- [ ] Rate limiting on API (optional)

### Documentation
- [ ] Update README with all modules
- [ ] Document API endpoints (OpenAPI/Swagger optional)
- [ ] Create admin user guide
- [ ] Troubleshooting guide
- [ ] Maintenance procedures

### Cutover Process

1. **Staging Test**
   - [ ] Deploy to staging environment
   - [ ] Full user acceptance testing
   - [ ] Load testing (if high traffic)
   - [ ] Security pen test (optional)

2. **Prepare Rollback**
   - [ ] Backup old admin files → `/admin-legacy/`
   - [ ] Document rollback procedure
   - [ ] Test rollback process
   - [ ] Have team standby during cutover

3. **Cutover Day**
   - [ ] Announce maintenance window
   - [ ] Stop old admin processes
   - [ ] Run any data migrations
   - [ ] Enable new admin panel
   - [ ] Redirect `/admin/` to `/admin-v2/` OR update links
   - [ ] Verify all workflows work
   - [ ] Monitor for errors
   - [ ] Have rollback ready

4. **Post-Cutover**
   - [ ] Monitor error logs for 24 hours
   - [ ] Gather admin feedback
   - [ ] Fix any critical issues
   - [ ] Document lessons learned
   - [ ] Plan archive of old files (day 30)

## Weekly Milestones

### Week 1-2 (COMPLETE ✅)
- [x] Foundation: Vite, React, Layout, Auth
- [x] Dashboard with module cards

### Week 3 (COMPLETE ✅)
- [x] SMR Pipeline: Upload, parse, map, finalize
- [x] 6 API endpoints
- [x] Full UI with file drag-drop

### Week 4 (COMPLETE ✅)
- [x] Rights Ledger: Registry, disputes, splits, certificates
- [x] 7 API endpoints
- [x] Registry table with filters
- [x] Dispute resolution workflow

### Week 5-6 (🟡 TODO)
- [ ] Royalties module
- [ ] EQS calculations
- [ ] Payout processing
- [ ] Stripe Connect integration

### Week 6-7 (🟡 TODO)
- [ ] Chart QA gatekeeper
- [ ] Manual corrections
- [ ] Dispute resolution
- [ ] Chart approval workflow

### Week 7-8 (🟡 TODO)
- [ ] Entity management (4 types)
- [ ] Moderation tools
- [ ] System dashboards
- [ ] Analytics

### Week 8 (🟡 TODO)
- [ ] Performance optimization
- [ ] Comprehensive testing
- [ ] Security audit
- [ ] Cutover planning

## Success Criteria

### Functional
- [x] All critical Bible workflows implemented
- [x] SMR pipeline: <5 minutes upload to finalize
- [x] Rights ledger: Full verification workflow
- [ ] Royalties: All calculations auditable
- [ ] Chart QA: All gates validating correctly
- [ ] Moderation: Reports to resolution in <2 days
- [ ] System: All monitoring working
- [ ] Analytics: Real-time metrics

### Performance
- [ ] Admin loads in <2 seconds
- [ ] Data tables handle 10k+ rows
- [ ] Search/filter in <500ms
- [ ] No memory leaks
- [ ] Smooth interactions (60 FPS)

### Security
- [ ] Zero authentication bypasses
- [ ] All inputs validated
- [ ] No XSS vulnerabilities
- [ ] No SQL injection vulnerabilities
- [ ] Audit log of admin actions

### User Experience
- [ ] Intuitive navigation
- [ ] Clear error messages
- [ ] Helpful tooltips/documentation
- [ ] Responsive on all devices
- [ ] Dark mode by default

### Stability
- [ ] <0.1% error rate
- [ ] <1 minute of unplanned downtime per month
- [ ] Graceful error handling
- [ ] Automatic retries for transient failures

## Known Issues & Workarounds

### Current Limitations
- [ ] Excel parsing not implemented (needs PhpSpreadsheet)
- [ ] Stripe Connect mock implementation
- [ ] Feature flags stored in memory (not persistent)
- [ ] Real-time updates use polling (not WebSocket)

### To Be Addressed
- [ ] Add WebSocket for real-time updates
- [ ] Implement Excel parsing
- [ ] Add persistent feature flags
- [ ] Add batch operations UI
- [ ] Add import/export functionality

## Post-Launch Maintenance

### Daily
- Monitor error logs
- Check API health
- Verify no failed payouts

### Weekly
- Review admin activity
- Check performance metrics
- Update any security patches

### Monthly
- Review user feedback
- Plan improvements
- Archive old logs

## Contact & Escalation

**Issues during migration:**
1. Check git history for context
2. Review MEMORY.md for decisions
3. Read README.md for architecture
4. Check DATABASE_SCHEMA_ADMIN_V2.md for schema

**Technical leads:**
- Erik - SMR workflow
- [Your role] - Royalties/Payouts
- [Your role] - Chart QA
- [Your role] - System operations

**Emergency rollback:**
1. Revert admin-v2 to serving static "maintenance" page
2. Restore `/admin/` from `/admin-legacy/`
3. Update redirect in index.php
4. Notify team of status

---

**Last Updated:** 2026-02-07
**Status:** Ready for Phase 3 (Royalties) implementation

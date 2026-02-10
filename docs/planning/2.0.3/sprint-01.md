# 🎯 NGN 2.0.3 Sprint 1: Blockchain Anchoring (Weeks 1-4)

**Sprint Duration:** Week of Feb 16 - Mar 13, 2026 (4 weeks)
**Phase:** 1 / 6 (Blockchain Integration)
**Owner:** Backend Lead + Blockchain Specialist
**Status:** [Pending kickoff]

---

## 📊 Sprint Overview

**Objective:** Deliver blockchain anchoring system with daily batch submissions to Polygon

**Key Deliverables:**
- ✅ Smart contract deployed to testnet
- ✅ Web3.js integration layer
- ✅ Database schema for blockchain
- ✅ Batch submission worker
- ✅ Blockchain verification endpoint
- ✅ Monitoring & alerting setup

**Success Criteria:**
- ✅ Smart contract passes security review
- ✅ Batch submissions working with <5% failure rate
- ✅ Load test passing (1K+ entries)
- ✅ All code reviewed and merged
- ✅ Team ready for Phase 2 (NFT minting)

**Sprint Velocity:** ~320 hours of work
**Team:** 1 Backend Lead + 1 Blockchain Specialist + occasional Full-Stack Engineer support

---

## 📅 Week-by-Week Breakdown

### WEEK 1: Smart Contract Design & Local Setup
**Dates:** Feb 16-20, 2026
**Owner:** Backend Lead + Blockchain Specialist
**Hours:** ~40 per person

#### Day 1 (Mon Feb 16): Kickoff & Environment Setup
**Time:** 8 hours (full day)

**Morning (4 hours):**
1. Team standup & review sprint goals
2. Distribute sprint-01.md to all contributors
3. Verify development environment setup
4. Confirm blockchain platform (Polygon Mumbai)
5. Test RPC connectivity: `curl https://rpc-mumbai.maticvigil.com`

**Deliverable:** Team ready, environment verified

**Afternoon (4 hours):**
1. Create Hardhat project structure
   ```bash
   mkdir ngn-blockchain-contracts
   cd ngn-blockchain-contracts
   npx hardhat init
   # Choose: Create TypeScript project
   ```
2. Setup contract directory structure
3. Create `.env` with testnet RPC URL
4. Verify Hardhat can compile sample contract
5. Document setup for team

**Deliverable:** Hardhat project ready

---

#### Day 2 (Tue Feb 17): Smart Contract Architecture
**Time:** 8 hours

**Morning (4 hours):**
1. Design smart contract specification:
   - Contract name: `ContentLedgerAnchor`
   - Functions:
     - `anchor(bytes32 merkleRoot)` - submit Merkle root
     - `verify(bytes32 contentHash)` - check if hash exists
     - `getTimestamp(bytes32 contentHash)` - get blockchain timestamp
     - `getTransactionHash(bytes32 merkleRoot)` - get tx hash for root
   - Events:
     - `AnchorSubmitted(bytes32 merkleRoot, uint256 timestamp)`
     - `HashVerified(bytes32 contentHash, bool exists)`
   - Access control: Admin-only anchor submissions

2. Document contract design in wiki/docs

**Deliverable:** Spec document for contract

**Afternoon (4 hours):**
1. Write Solidity contract based on spec
   - ERC-712 compliance check
   - Storage: mapping(bytes32 => uint256) roots
   - Storage: mapping(bytes32 => bool) verified hashes
   - Modifiers: onlyAdmin, nonReentrant
2. Create contract file: `contracts/ContentLedgerAnchor.sol`
3. Add NatSpec comments for all functions
4. Estimate gas costs for main operations

**Deliverable:** Initial Solidity contract written

---

#### Day 3 (Wed Feb 18): Unit Tests & Compilation
**Time:** 8 hours

**Morning (4 hours):**
1. Create test suite: `test/ContentLedgerAnchor.test.ts`
2. Write unit tests:
   - Test contract deployment
   - Test anchor submission
   - Test merkle root storage
   - Test hash verification
   - Test access control (only admin can anchor)
   - Test event emissions
3. Setup test fixtures and helpers

**Deliverable:** Test suite written

**Afternoon (4 hours):**
1. Run tests: `npx hardhat test`
2. Fix compilation errors
3. Get test coverage: `npx hardhat coverage`
4. Target: >80% coverage
5. Document any issues found
6. Create test documentation

**Deliverable:** All tests passing, >80% coverage

---

#### Day 4 (Thu Feb 19): Testnet Preparation
**Time:** 8 hours

**Morning (4 hours):**
1. Create deployment script: `scripts/deploy.ts`
2. Prepare testnet wallet:
   - Generate new wallet OR use existing test wallet
   - Fund with Polygon Mumbai testnet MATIC (~1-2 MATIC)
   - Verify balance: Use PolygonScan Mumbai explorer
3. Setup environment variables for testnet

**Deliverable:** Deployment script ready, testnet wallet funded

**Afternoon (4 hours):**
1. Deploy to testnet: `npx hardhat run scripts/deploy.ts --network mumbai`
2. Record contract address: `0x[testnet_address]`
3. Verify deployment on PolygonScan Mumbai
4. Test basic contract function calls via Hardhat console
5. Document deployment steps

**Deliverable:** Contract deployed to Mumbai testnet, verified

---

#### Day 5 (Fri Feb 20): Week 1 Review & Documentation
**Time:** 8 hours

**Morning (4 hours):**
1. Code review with team (ideally 2+ reviewers)
2. Address any feedback
3. Merge contracts to main branch (or feature branch if preferred)
4. Tag release: `v2.0.3-phase1-week1`

**Deliverable:** Code reviewed and merged

**Afternoon (4 hours):**
1. Write Week 1 documentation:
   - Smart contract architecture overview
   - Test coverage report
   - Deployment instructions
   - Known limitations
2. Start Bible Chapter 43 outline
3. Update sprint-01.md with actual hours spent
4. Prepare for Week 2: "Testnet Testing & Optimization"

**Deliverable:** Documentation complete, week reviewed

---

**WEEK 1 SUMMARY:**
- ✅ Hardhat project setup
- ✅ Smart contract written
- ✅ Unit tests passing (>80% coverage)
- ✅ Contract deployed to Mumbai testnet
- ✅ Ready for Week 2: Integration testing

---

### WEEK 2: Integration, Optimization & Error Handling
**Dates:** Feb 23-27, 2026
**Owner:** Backend Lead + Blockchain Specialist
**Hours:** ~40 per person

#### Day 1 (Mon Feb 23): Web3.js Integration Layer
**Time:** 8 hours

**Morning (4 hours):**
1. Create PHP wrapper class: `lib/Blockchain/BlockchainService.php`
2. Implement Web3.js integration:
   - Constructor: Initialize web3 with Polygon RPC URL
   - Method: `submitMerkleRoot(bytes32 $root): array`
   - Method: `verifyHash(bytes32 $hash): bool`
   - Method: `getTimestamp(bytes32 $hash): int`
   - Error handling: Wrap Web3 exceptions in custom exceptions
3. Add logging for all blockchain operations

**Deliverable:** BlockchainService class written

**Afternoon (4 hours):**
1. Write integration tests in PHP:
   - Test submitMerkleRoot with testnet contract
   - Test verifyHash lookup
   - Test error handling
2. Use testnet contract address from Week 1
3. Verify transaction hashes are stored correctly
4. Test response format

**Deliverable:** Integration tests passing with testnet contract

---

#### Day 2 (Tue Feb 24): Database Schema Design
**Time:** 8 hours

**Morning (4 hours):**
1. Design blockchain-related database tables:
   ```sql
   CREATE TABLE blockchain_anchors (
     id INT PRIMARY KEY AUTO_INCREMENT,
     merkle_root VARCHAR(64) UNIQUE,
     blockchain_tx_hash VARCHAR(66),
     blockchain_status ENUM('pending', 'confirmed', 'failed'),
     block_number INT,
     submitted_at TIMESTAMP,
     confirmed_at TIMESTAMP NULL,
     gas_price_gwei DECIMAL(10,2),
     gas_used INT,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   CREATE TABLE blockchain_transactions (
     id INT PRIMARY KEY AUTO_INCREMENT,
     tx_hash VARCHAR(66) UNIQUE,
     anchor_id INT,
     from_address VARCHAR(42),
     to_address VARCHAR(42),
     value_wei BIGINT,
     gas_price_gwei DECIMAL(10,2),
     gas_used INT,
     block_number INT,
     status ENUM('pending', 'confirmed', 'failed'),
     error_message TEXT,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     FOREIGN KEY (anchor_id) REFERENCES blockchain_anchors(id)
   );
   ```
2. Create migration script: `scripts/2026_02_23_blockchain_tables.sql`

**Deliverable:** Database schema designed and documented

**Afternoon (4 hours):**
1. Review schema with team
2. Optimize indexes:
   - Index on merkle_root (for lookups)
   - Index on blockchain_tx_hash
   - Index on blockchain_status (for filtering pending)
   - Index on submitted_at (for date range queries)
3. Add foreign key constraints
4. Write database migration code in PHP
5. Test migration on local database

**Deliverable:** Migration script ready and tested

---

#### Day 3 (Wed Feb 25): Merkle Tree Implementation
**Time:** 8 hours

**Morning (4 hours):**
1. Create Merkle tree class: `lib/Blockchain/MerkleTree.php`
2. Implement algorithm:
   - Constructor: Accept array of hashes
   - Method: `getRoot(): string` - compute Merkle root
   - Method: `getProof(string $hash): array` - get proof for hash
   - Algorithm: Standard Merkle tree (left-pad odd nodes)
3. Add validation for hash format (64-char hex)

**Deliverable:** MerkleTree class implemented

**Afternoon (4 hours):**
1. Write unit tests for MerkleTree:
   - Test with 1 hash (root = hash)
   - Test with 2 hashes
   - Test with 10 hashes
   - Test with 1000+ hashes (performance)
   - Verify root matches smart contract calculation
2. Performance test:
   - 1K hashes: should complete in <500ms
   - 10K hashes: should complete in <5s
3. Document algorithm and usage

**Deliverable:** MerkleTree tested and documented

---

#### Day 4 (Thu Feb 26): Batch Submission Worker
**Time:** 8 hours

**Morning (4 hours):**
1. Design batch submission flow:
   - Query pending ledger entries from content_ledger
   - Group into batches (configurable, e.g., 100 at a time)
   - Calculate Merkle root for batch
   - Submit to blockchain via Web3.js
   - Wait for confirmation (configurable timeout)
   - Update database with tx_hash and status
2. Create worker class: `lib/Blockchain/BatchSubmissionWorker.php`
3. Implement error handling:
   - Retry logic (exponential backoff)
   - Gas price monitoring
   - Fallback for failed submissions (queue for next batch)

**Deliverable:** BatchSubmissionWorker class written

**Afternoon (4 hours):**
1. Create CLI command: `bin/blockchain/submit-batch.php`
2. Test worker with test data:
   - Create 100 test ledger entries
   - Run worker against testnet
   - Verify Merkle root submitted
   - Check database updates
3. Test error scenarios:
   - Network timeout
   - Invalid gas price
   - Contract revert
4. Document usage and configuration

**Deliverable:** CLI command working, tested with testnet

---

#### Day 5 (Fri Feb 27): Week 2 Review & Gas Optimization
**Time:** 8 hours

**Morning (4 hours):**
1. Code review of all Week 2 code
2. Address feedback
3. Merge to main
4. Tag: `v2.0.3-phase1-week2`

**Deliverable:** Code reviewed and merged

**Afternoon (4 hours):**
1. Analyze gas costs:
   - Estimate cost per anchor submission (~$1-10 depending on gas)
   - Document gas optimization strategies
   - Analyze batch size vs. gas efficiency
2. Update documentation
3. Create Week 2 summary
4. Prepare for Week 3: "Database Integration & Monitoring"

**Deliverable:** Gas analysis complete, optimization documented

---

**WEEK 2 SUMMARY:**
- ✅ Web3.js integration layer (PHP)
- ✅ Database schema designed and migrated
- ✅ Merkle tree algorithm implemented
- ✅ Batch submission worker working
- ✅ Integration tested on testnet
- ✅ Gas costs analyzed

---

### WEEK 3: Monitoring & Certificate Integration
**Dates:** Mar 2-6, 2026
**Owner:** Backend Lead + Full-Stack Engineer (supporting)
**Hours:** ~40 per person

#### Day 1 (Mon Mar 2): Blockchain Status Monitoring
**Time:** 8 hours

**Morning (4 hours):**
1. Create monitoring class: `lib/Blockchain/BlockchainMonitor.php`
2. Implement checks:
   - Check blockchain connectivity (ping RPC)
   - Check contract deployment status
   - Count pending batches (waiting for confirmation)
   - Track average confirmation time
   - Monitor gas price trends
3. Add alerting:
   - Alert if <90% confirmations in last 24h
   - Alert if average confirmation time >4 hours
   - Alert if contract unreachable

**Deliverable:** Monitoring class with alerting

**Afternoon (4 hours):**
1. Create admin dashboard endpoint: `public/admin/blockchain-status.php`
2. Display:
   - Current network status (connected/disconnected)
   - Recent submissions (last 10)
   - Confirmation rate (%)
   - Average gas price (GWEI)
   - Pending batches
3. Add real-time updates (AJAX or polling)
4. Test dashboard

**Deliverable:** Admin dashboard created and tested

---

#### Day 2 (Tue Mar 3): Certificate Integration
**Time:** 8 hours

**Morning (4 hours):**
1. Update certificate generation to include blockchain proof
2. Modify `lib/Legal/DigitalCertificateService.php`:
   - Add method: `addBlockchainProof()`
   - Include blockchain_tx_hash in certificate display
   - Add link to PolygonScan for verification
3. Update certificate template with blockchain section:
   - Show Merkle root if available
   - Show transaction hash if confirmed
   - Add "Verify on Blockchain" button

**Deliverable:** Certificate template updated

**Afternoon (4 hours):**
1. Create verification API endpoint: `public/api/v1/blockchain/verify.php`
2. Endpoint features:
   - GET /api/v1/blockchain/verify?hash=0x...
   - Returns: blockchainTxHash, merklRoot, blockNumber, timestamp
   - Public API (no auth required)
   - Caching for recently verified hashes
3. Test endpoint with various queries
4. Document API

**Deliverable:** Blockchain verification endpoint working

---

#### Day 3 (Wed Mar 4): Load Testing
**Time:** 8 hours

**Morning (4 hours):**
1. Create load test script: `scripts/load-test-blockchain.php`
2. Test scenario:
   - Create 1,000 test ledger entries
   - Submit batch to testnet
   - Track confirmation times
   - Record gas costs
3. Measure:
   - Time to compute Merkle root (for 1K entries)
   - Time to submit to blockchain
   - Average confirmation time
   - Database query performance

**Deliverable:** Load test script created

**Afternoon (4 hours):**
1. Run load test against testnet
2. Document results:
   - Merkle root computation: [X] ms
   - Blockchain submission: [X] ms
   - Average confirmation time: [X] min
   - Total gas cost: [X] GWEI
3. Identify bottlenecks
4. Optimize if needed:
   - Index additions
   - Query optimization
   - Batch size tuning

**Deliverable:** Load test completed, bottlenecks identified

---

#### Day 4 (Thu Mar 5): Error Handling & Retry Logic
**Time:** 8 hours

**Morning (4 hours):**
1. Implement comprehensive error handling:
   - Network errors (RPC timeout, etc.)
   - Smart contract errors (revert, out of gas, etc.)
   - Database errors (constraint violations, etc.)
2. Implement retry logic:
   - Exponential backoff (1s, 2s, 4s, 8s, 16s)
   - Max retries: 5
   - Store failed submissions in queue table
3. Create fallback mechanism:
   - If blockchain fails: Non-blocking (log and continue)
   - Queue failed submissions for manual review

**Deliverable:** Error handling and retry logic implemented

**Afternoon (4 hours):**
1. Test error scenarios:
   - Simulate network timeout
   - Simulate contract revert
   - Simulate out of gas
   - Verify retry logic works
   - Verify queue system captures failures
2. Document error codes and meanings
3. Create runbook for common errors

**Deliverable:** Error handling tested, runbook created

---

#### Day 5 (Fri Mar 6): Week 3 Review & Phase 1 Wrap-Up
**Time:** 8 hours

**Morning (4 hours):**
1. Complete code review
2. Address all feedback
3. Merge to main
4. Tag: `v2.0.3-phase1-week3`

**Deliverable:** Code reviewed and merged

**Afternoon (4 hours):**
1. Create Phase 1 summary document:
   - What was built
   - What works well
   - Known limitations
   - Next steps (Phase 2)
2. Update Bible Chapter 43 with blockchain section
3. Create handoff documentation for Phase 2
4. Document any technical debt or follow-up items
5. Team retrospective (if time allows)

**Deliverable:** Phase 1 documentation complete

---

**WEEK 3 SUMMARY:**
- ✅ Monitoring and alerting operational
- ✅ Certificate integration complete
- ✅ Blockchain verification API working
- ✅ Load testing completed
- ✅ Error handling comprehensive
- ✅ Ready for Phase 2

---

### WEEK 4: Final Testing, Optimization & Production Prep
**Dates:** Mar 9-13, 2026
**Owner:** Backend Lead + Full-Stack Engineer + QA
**Hours:** ~40 per person

#### Day 1 (Mon Mar 9): End-to-End Testing
**Time:** 8 hours

**Morning (4 hours):**
1. Create end-to-end test suite
2. Test complete flow:
   - Create ledger entry (via API or UI)
   - Batch includes new entry
   - Submit batch to blockchain
   - Verify transaction on blockchain
   - Certificate shows blockchain proof
   - Verification API returns correct data
3. Run multiple times to verify consistency

**Deliverable:** E2E test suite passing

**Afternoon (4 hours):**
1. Test edge cases:
   - Single entry batch
   - Large batch (1K+ entries)
   - Duplicate hashes (should be rejected)
   - Invalid certificate IDs
   - Offline blockchain (graceful degradation)
2. Document edge cases and handling

**Deliverable:** Edge cases tested and documented

---

#### Day 2 (Tue Mar 10): Performance Optimization
**Time:** 8 hours

**Morning (4 hours):**
1. Profile database queries:
   - Use EXPLAIN ANALYZE on Merkle tree queries
   - Identify slow queries
   - Add indexes if needed
2. Cache Merkle roots (if same entries processed again)
3. Optimize Web3.js calls:
   - Batch RPC calls where possible
   - Use connection pooling

**Deliverable:** Performance optimized

**Afternoon (4 hours):**
1. Run performance benchmarks:
   - Ledger query time
   - Merkle root computation time
   - Web3.js submission time
   - Certificate generation time
2. Compare to Week 3 results
3. Document performance metrics
4. Set performance baselines for monitoring

**Deliverable:** Performance benchmarks documented

---

#### Day 3 (Wed Mar 11): Production Preparation
**Time:** 8 hours

**Morning (4 hours):**
1. Prepare for mainnet (when ready):
   - Create mainnet configuration (separate from testnet)
   - Secure mainnet wallet setup
   - Document mainnet RPC endpoints
   - Plan for transaction costs
   - Set up production monitoring
2. Create deployment checklist for mainnet

**Deliverable:** Mainnet preparation checklist

**Afternoon (4 hours):**
1. Create operational documentation:
   - How to start batch worker
   - How to monitor status
   - How to handle errors
   - How to pause/resume submissions
   - Rollback procedures
2. Train team on operational procedures
3. Create on-call runbook

**Deliverable:** Operational documentation complete

---

#### Day 4 (Thu Mar 12): Security Review & Final Testing
**Time:** 8 hours

**Morning (4 hours):**
1. Security checklist:
   - Private keys stored securely (not in code)
   - Input validation on all blockchain data
   - Rate limiting on verification API
   - No sensitive data in logs
   - CORS properly configured
2. Run security scan (automated)
3. Manual security review with team

**Deliverable:** Security review complete

**Afternoon (4 hours):**
1. Final integration test
2. Smoke test of all components
3. Test rollback procedures
4. Create pre-Phase-2 checklist
5. Document any remaining issues

**Deliverable:** Final testing complete, all components working

---

#### Day 5 (Fri Mar 13): Sprint 1 Completion & Handoff
**Time:** 8 hours

**Morning (4 hours):**
1. Final code review
2. Address all feedback
3. Merge to main
4. Tag: `v2.0.3-phase1-complete`
5. Create release notes

**Deliverable:** Code reviewed, tagged, released

**Afternoon (4 hours):**
1. Create Phase 1 completion report:
   - All tasks completed: ✅
   - All success criteria met: ✅
   - Performance metrics: [X]
   - Gas costs: [X]
   - Issues encountered: [list]
   - Solutions implemented: [list]
2. Update progress-beta-2.0.3.json:
   ```json
   {
     "phase": "1_complete",
     "completion_percentage": 100,
     "actual_effort_weeks": 4,
     "budget_used": "$[X]",
     "blockers_encountered": 0,
     "ready_for_phase_2": true
   }
   ```
3. Create handoff documentation for Phase 2 (NFT minting)
4. Team celebration & retrospective

**Deliverable:** Phase 1 complete, documented, handed off

---

**WEEK 4 SUMMARY:**
- ✅ End-to-end testing complete
- ✅ Performance optimized
- ✅ Production preparation ready
- ✅ Security review passed
- ✅ Phase 1 complete and documented
- ✅ **READY FOR PHASE 2: NFT MINTING**

---

## 📊 Sprint 1 Success Metrics

| Metric | Target | Actual | Status |
|--------|--------|--------|--------|
| **All Phase 1 tasks completed** | 100% | [TBD] | [Pending] |
| **Smart contract security** | Audit passed | [TBD] | [Pending] |
| **Code coverage** | >80% | [TBD] | [Pending] |
| **Load test performance** | <5s for 1K entries | [TBD] | [Pending] |
| **Blockchain failure rate** | <5% | [TBD] | [Pending] |
| **Average confirmation time** | <10 minutes | [TBD] | [Pending] |
| **Gas cost per submission** | <$50 | [TBD] | [Pending] |
| **Documentation complete** | Bible Ch. 43 | [TBD] | [Pending] |
| **All code reviewed** | 2+ reviewers | [TBD] | [Pending] |
| **Timeline adherence** | On schedule | [TBD] | [Pending] |

---

## 🚀 Parallel Work (Not Blocking)

While Phase 1 is executing:

**Full-Stack Engineer** (supporting Phase 1 + starting Phase 2 prep):
- ✅ Start Phase 3 (Admin Dashboard) design
- ✅ Create UI mockups
- ✅ Plan database schema for admin features
- ✅ Start React SPA structure

**Project Manager**:
- ✅ Track Phase 1 progress
- ✅ Manage risks and blockers
- ✅ Maintain communication
- ✅ Update progress files weekly

---

## 📞 Sprint Communication

**Daily Standup:** 9:30 AM (10 minutes, Slack OK if remote)
```
- Yesterday: [What I completed]
- Today: [What I'm working on]
- Blockers: [Any issues/help needed]
```

**Weekly Meeting:** Wed 2 PM (30-45 minutes)
- Full team sync
- Progress review
- Blocker resolution
- Adjust plan if needed

**Status Updates:** Friday EOD
- Update progress-beta-2.0.3.json
- Report to leadership
- Note any issues

---

## 🎯 Definition of Done (for each task)

For a task to be considered "done":
- [ ] Code written and tested locally
- [ ] Unit tests passing (>80% coverage)
- [ ] Code reviewed by 2+ engineers
- [ ] Peer reviewed for security
- [ ] Merged to main branch
- [ ] Documentation updated (wiki, Bible, code comments)
- [ ] No new high-priority issues introduced
- [ ] Tested on testnet (if applicable)

---

## 📚 Key Resources

**Documentation:**
- 203-Complete-Plan.md - Full roadmap
- DEVELOPMENT-COST-ANALYSIS.md - Budget context
- Polygon documentation: https://polygon.technology/
- Hardhat docs: https://hardhat.org/
- Web3.js docs: https://web3js.readthedocs.io/

**Tools:**
- Hardhat: Smart contract development
- Polygon Mumbai: Testnet for testing
- PolygonScan Mumbai: Block explorer for testnet
- MetaMask: Testnet wallet management

---

## ✅ Sprint Readiness

Before starting Week 1:

- [ ] All team members have development environment setup
- [ ] GitHub project board created
- [ ] Slack channel #2.0.3-development created
- [ ] First standup scheduled (Mon 9:30 AM)
- [ ] Polygon Mumbai testnet wallet funded
- [ ] Sprint documentation reviewed by team
- [ ] Smart contract auditor contacted for quote

---

**🚀 READY TO START SPRINT 1!**

**Created:** February 9, 2026
**Next Update:** After Week 1 completion (Feb 21)
**Status:** READY FOR EXECUTION
**Confidence:** HIGH (plan validated, team ready, blockchain tools familiar)

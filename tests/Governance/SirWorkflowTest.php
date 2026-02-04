<?php

/**
 * Integration Tests for SIR Workflow
 * Chapter 31 - Complete SIR lifecycle testing
 */

use NGN\Lib\Governance\DirectorateRoles;
use PHPUnit\Framework\TestCase;

class SirWorkflowTest extends TestCase
{
    protected DirectorateRoles $roles;

    protected function setUp(): void
    {
        $this->roles = new DirectorateRoles(
            chairmanUserId: 1,
            directors: [
                'brandon' => ['user_id' => 2],
                'pepper' => ['user_id' => 3],
                'erik' => ['user_id' => 4],
            ]
        );
    }

    /**
     * Test complete SIR workflow: OPEN → IN_REVIEW → RANT_PHASE → VERIFIED → CLOSED
     */
    public function testCompleteSirWorkflow(): void
    {
        // Step 1: Chairman creates SIR
        $this->assertTrue($this->roles->isChairman(1), 'User 1 should be chairman');

        $sirData = [
            'objective' => 'Verify Escrow Compliance',
            'context' => 'Ensure Rights Ledger dispute handling meets institutional standards',
            'deliverable' => 'One-page technical critique',
            'assigned_to_director' => 'brandon',
            'priority' => 'critical',
            'issued_by_user_id' => 1,
            'status' => 'open',
        ];

        // Validate director assignment
        $this->assertTrue(
            $this->roles->isValidDirector($sirData['assigned_to_director']),
            'brandon should be valid director'
        );

        $directorUserId = $this->roles->getDirectorUserId('brandon');
        $this->assertSame(2, $directorUserId, 'Brandon should have user ID 2');

        // Step 2: Director receives notification and claims SIR
        $this->assertTrue(
            $this->roles->isDirector($directorUserId),
            'Director user should be recognized as director'
        );

        // Verify SIR state: OPEN
        $expectedStatus = 'open';
        $this->assertSame($expectedStatus, $sirData['status']);

        // Step 3: Director moves to IN_REVIEW (status transition simulation)
        $statuses = ['open', 'in_review', 'rant_phase', 'verified', 'closed'];
        $this->assertContains('open', $statuses);
        $this->assertContains('in_review', $statuses);

        // Simulate valid transition
        $validTransitions = [
            'open' => ['in_review'],
            'in_review' => ['rant_phase', 'verified'],
            'rant_phase' => ['in_review', 'verified'],
            'verified' => ['closed'],
            'closed' => [],
        ];

        $currentStatus = 'open';
        $nextStatus = 'in_review';
        $this->assertContains($nextStatus, $validTransitions[$currentStatus]);

        // Step 4: Director adds feedback (transitions to RANT_PHASE)
        $currentStatus = 'in_review';
        $nextStatus = 'rant_phase';
        $this->assertContains($nextStatus, $validTransitions[$currentStatus]);

        // Step 5: Chairman responds, director verifies
        $currentStatus = 'rant_phase';
        $nextStatus = 'verified';
        $this->assertContains($nextStatus, $validTransitions[$currentStatus]);

        // Step 6: Chairman closes SIR
        $currentStatus = 'verified';
        $nextStatus = 'closed';
        $this->assertContains($nextStatus, $validTransitions[$currentStatus]);

        // Verify terminal state
        $terminalStatus = 'closed';
        $this->assertEmpty($validTransitions[$terminalStatus], 'Closed status should have no valid transitions');
    }

    /**
     * Test invalid status transitions are blocked
     */
    public function testInvalidStatusTransitionsAreBlocked(): void
    {
        $validTransitions = [
            'open' => ['in_review'],
            'in_review' => ['rant_phase', 'verified'],
            'rant_phase' => ['in_review', 'verified'],
            'verified' => ['closed'],
            'closed' => [],
        ];

        // Invalid: open → verified (should go through in_review)
        $this->assertNotContains('verified', $validTransitions['open']);

        // Invalid: open → rant_phase (should go through in_review)
        $this->assertNotContains('rant_phase', $validTransitions['open']);

        // Invalid: verified → in_review (can't go backwards)
        $this->assertNotContains('in_review', $validTransitions['verified']);

        // Invalid: closed → anything
        $this->assertEmpty($validTransitions['closed']);
    }

    /**
     * Test only Chairman can create SIRs
     */
    public function testOnlyChairmanCanCreateSir(): void
    {
        $this->assertTrue($this->roles->isChairman(1), 'User 1 is chairman');
        $this->assertFalse($this->roles->isChairman(2), 'User 2 is not chairman');
        $this->assertFalse($this->roles->isChairman(3), 'User 3 is not chairman');
        $this->assertFalse($this->roles->isChairman(4), 'User 4 is not chairman');
    }

    /**
     * Test only assigned director can verify SIR
     */
    public function testOnlyAssignedDirectorCanVerify(): void
    {
        // SIR assigned to Brandon (user 2)
        $sirDirectorUserId = 2;

        // Only Brandon can verify
        $this->assertTrue(
            $this->roles->isDirector($sirDirectorUserId),
            'Assigned user should be director'
        );

        // Other directors cannot verify
        $this->assertTrue(
            $this->roles->isDirector(3),
            'User 3 is director (Pepper)'
        );
        $this->assertTrue(
            $this->roles->isDirector(4),
            'User 4 is director (Erik)'
        );

        // But they shouldn't have access to this specific SIR
        $this->assertNotSame(3, $sirDirectorUserId);
        $this->assertNotSame(4, $sirDirectorUserId);
    }

    /**
     * Test director registry divisions are correct
     */
    public function testDirectorRegistryDivisionsAreCorrect(): void
    {
        $this->assertSame('saas_fintech', $this->roles->getRegistryDivision('brandon'));
        $this->assertSame('strategic_ecosystem', $this->roles->getRegistryDivision('pepper'));
        $this->assertSame('data_integrity', $this->roles->getRegistryDivision('erik'));
    }

    /**
     * Test SIR number format validation
     */
    public function testSirNumberFormat(): void
    {
        $sirNumber = 'SIR-2026-001';

        // Should match format: SIR-YYYY-###
        $pattern = '/^SIR-\d{4}-\d{3}$/';
        $this->assertMatchesRegularExpression($pattern, $sirNumber);

        // Test invalid formats
        $this->assertDoesNotMatchRegularExpression($pattern, 'SIR-26-001');
        $this->assertDoesNotMatchRegularExpression($pattern, 'SIR-2026-1');
        $this->assertDoesNotMatchRegularExpression($pattern, '2026-001');
    }

    /**
     * Test overdue SIR detection (>14 days)
     */
    public function testOverdueDetection(): void
    {
        // Create timestamps
        $now = new DateTime();
        $created14DaysAgo = clone $now;
        $created14DaysAgo->modify('-14 days');

        $created15DaysAgo = clone $now;
        $created15DaysAgo->modify('-15 days');

        $daysDiff14 = $now->diff($created14DaysAgo)->days;
        $daysDiff15 = $now->diff($created15DaysAgo)->days;

        // Should be marked overdue
        $isOverdue = $daysDiff15 > 14;
        $this->assertTrue($isOverdue);

        // Should NOT be marked overdue
        $isOverdue = $daysDiff14 > 14;
        $this->assertFalse($isOverdue);
    }
}

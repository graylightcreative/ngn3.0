<?php

/**
 * Unit Tests for SirAuditService
 * Chapter 31 - Audit logging verification
 */

use NGN\Lib\Governance\SirAuditService;
use PHPUnit\Framework\TestCase;

class SirAuditServiceTest extends TestCase
{
    protected SirAuditService $auditService;
    protected \PDO $mockPdo;

    protected function setUp(): void
    {
        // Create mock PDO for testing
        $this->mockPdo = $this->createMock(\PDO::class);
        $this->auditService = new SirAuditService($this->mockPdo);
    }

    public function testLogCreatedInsertsAuditEntry(): void
    {
        // Mock the prepare and execute methods
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->isType('array'));

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        // Call logCreated
        $this->auditService->logCreated(
            sirId: 1,
            actorUserId: 1,
            sirData: [
                'sir_number' => 'SIR-2026-001',
                'objective' => 'Test Objective',
                'priority' => 'critical',
                'assigned_to_director' => 'brandon',
            ]
        );

        // If we reach here without exception, test passes
        $this->assertTrue(true);
    }

    public function testLogStatusChangeTracksTransition(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->isType('array'));

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        // Call logStatusChange
        $this->auditService->logStatusChange(
            sirId: 1,
            oldStatus: 'open',
            newStatus: 'in_review',
            actorUserId: 2,
            actorRole: 'director'
        );

        $this->assertTrue(true);
    }

    public function testGetAuditTrailReturnsChronologicalOrder(): void
    {
        // Mock data that would be returned from database
        $mockData = [
            [
                'audit_id' => 1,
                'action' => 'created',
                'old_status' => null,
                'new_status' => null,
                'actor_user_id' => 1,
                'actor_role' => 'chairman',
                'change_details' => null,
                'created_at' => '2026-01-23 10:00:00',
            ],
            [
                'audit_id' => 2,
                'action' => 'status_change',
                'old_status' => 'open',
                'new_status' => 'in_review',
                'actor_user_id' => 2,
                'actor_role' => 'director',
                'change_details' => json_encode(['transition' => 'open → in_review']),
                'created_at' => '2026-01-23 14:30:00',
            ],
        ];

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($mockData);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        $trail = $this->auditService->getAuditTrail(1);

        // Verify chronological order
        $this->assertSame('created', $trail[0]['action']);
        $this->assertSame('status_change', $trail[1]['action']);
    }

    public function testLogFeedbackAddedTracksComment(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with($this->isType('array'));

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        $this->auditService->logFeedbackAdded(
            sirId: 1,
            feedbackId: 5,
            actorUserId: 2,
            actorRole: 'director'
        );

        $this->assertTrue(true);
    }

    public function testVerifyIntegrityChecksAuditLog(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->expects($this->once())
            ->method('execute')
            ->with([1]);
        $mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(['total_entries' => 5]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        $result = $this->auditService->verifyIntegrity(1);

        $this->assertSame(1, $result['sir_id']);
        $this->assertSame(5, $result['total_entries']);
        $this->assertSame('intact', $result['status']);
    }
}

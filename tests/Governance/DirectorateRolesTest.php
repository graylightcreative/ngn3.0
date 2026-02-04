<?php

/**
 * Unit Tests for DirectorateRoles
 * Chapter 31 - Director role mapping
 */

use NGN\Lib\Governance\DirectorateRoles;
use PHPUnit\Framework\TestCase;

class DirectorateRolesTest extends TestCase
{
    protected DirectorateRoles $roles;

    protected function setUp(): void
    {
        // Initialize with test director IDs
        $this->roles = new DirectorateRoles(
            chairmanUserId: 1,
            directors: [
                'brandon' => ['user_id' => 2],
                'pepper' => ['user_id' => 3],
                'erik' => ['user_id' => 4],
            ]
        );
    }

    public function testGetDirectorUserIdReturnsCorrectId(): void
    {
        $this->assertSame(2, $this->roles->getDirectorUserId('brandon'));
        $this->assertSame(3, $this->roles->getDirectorUserId('pepper'));
        $this->assertSame(4, $this->roles->getDirectorUserId('erik'));
    }

    public function testGetDirectorUserIdReturnNullForInvalidDirector(): void
    {
        $this->assertNull($this->roles->getDirectorUserId('invalid'));
    }

    public function testGetDirectorNameReturnsCorrectName(): void
    {
        $this->assertSame('Brandon Lamb', $this->roles->getDirectorName('brandon'));
        $this->assertSame('Pepper Gomez', $this->roles->getDirectorName('pepper'));
        $this->assertSame('Erik Baker', $this->roles->getDirectorName('erik'));
    }

    public function testGetRegistryDivisionReturnsCorrectDivision(): void
    {
        $this->assertSame('saas_fintech', $this->roles->getRegistryDivision('brandon'));
        $this->assertSame('strategic_ecosystem', $this->roles->getRegistryDivision('pepper'));
        $this->assertSame('data_integrity', $this->roles->getRegistryDivision('erik'));
    }

    public function testIsDirectorReturnsTrueForValidDirector(): void
    {
        $this->assertTrue($this->roles->isDirector(2));
        $this->assertTrue($this->roles->isDirector(3));
        $this->assertTrue($this->roles->isDirector(4));
    }

    public function testIsDirectorReturnsFalseForNonDirector(): void
    {
        $this->assertFalse($this->roles->isDirector(1)); // Chairman is not a director
        $this->assertFalse($this->roles->isDirector(99));
    }

    public function testIsChairmanReturnsTrueForChairman(): void
    {
        $this->assertTrue($this->roles->isChairman(1));
    }

    public function testIsChairmanReturnsFalseForNonChairman(): void
    {
        $this->assertFalse($this->roles->isChairman(2));
        $this->assertFalse($this->roles->isChairman(3));
        $this->assertFalse($this->roles->isChairman(4));
    }

    public function testGetDirectorSlugReturnsCorrectSlug(): void
    {
        $this->assertSame('brandon', $this->roles->getDirectorSlug(2));
        $this->assertSame('pepper', $this->roles->getDirectorSlug(3));
        $this->assertSame('erik', $this->roles->getDirectorSlug(4));
    }

    public function testGetDirectorSlugReturnsNullForInvalidUserId(): void
    {
        $this->assertNull($this->roles->getDirectorSlug(99));
    }

    public function testIsValidDirectorReturnsTrueForValidSlugs(): void
    {
        $this->assertTrue($this->roles->isValidDirector('brandon'));
        $this->assertTrue($this->roles->isValidDirector('pepper'));
        $this->assertTrue($this->roles->isValidDirector('erik'));
    }

    public function testIsValidDirectorReturnsFalseForInvalidSlugs(): void
    {
        $this->assertFalse($this->roles->isValidDirector('invalid'));
        $this->assertFalse($this->roles->isValidDirector('chairman'));
    }

    public function testGetChairmanUserIdReturnsCorrectId(): void
    {
        $this->assertSame(1, $this->roles->getChairmanUserId());
    }

    public function testGetDirectorUserIdsReturnsAllDirectorIds(): void
    {
        $ids = $this->roles->getDirectorUserIds();
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
        $this->assertContains(4, $ids);
        $this->assertCount(3, $ids);
    }

    public function testGetDirectorSlugsReturnsAllSlugs(): void
    {
        $slugs = $this->roles->getDirectorSlugs();
        $this->assertContains('brandon', $slugs);
        $this->assertContains('pepper', $slugs);
        $this->assertContains('erik', $slugs);
        $this->assertCount(3, $slugs);
    }
}

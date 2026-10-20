<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\ReplaceText;
use App\Domain\Exception\TextVersionConflict;
use App\Repository\TextRepository;
use Doctrine\ORM\OptimisticLockException;

final class ReplaceTextTest extends IntegrationTestCase
{
    public function testPersistsTheContentAndBumpsTheVersion(): void
    {
        $text = $this->service(ReplaceText::class)('hello', 1);

        self::assertSame(2, $text->getVersion());
        self::assertSame(['content' => 'hello', 'version' => 2, 'updated_at' => '2026-10-18 12:00:00'], $this->storedRow());
    }

    public function testDoesNotFlushAnIdenticalContent(): void
    {
        $replace = $this->service(ReplaceText::class);
        $replace('hello', 1);
        self::mockTime('2026-10-18 13:00:00');

        $text = $replace('hello', 2);

        self::assertSame(2, $text->getVersion());
        self::assertSame(['content' => 'hello', 'version' => 2, 'updated_at' => '2026-10-18 12:00:00'], $this->storedRow());
    }

    public function testRejectsAnEditBasedOnAStaleVersionWithoutWriting(): void
    {
        $replace = $this->service(ReplaceText::class);
        $replace('first', 1);

        try {
            $replace('mine', 1);
            self::fail('A stale base version must be refused.');
        } catch (TextVersionConflict) {
        }

        self::assertSame(['content' => 'first', 'version' => 2, 'updated_at' => '2026-10-18 12:00:00'], $this->storedRow());
    }

    public function testStampsTheReplacementWithTheCurrentTime(): void
    {
        $replace = $this->service(ReplaceText::class);
        $replace('first', 1);
        self::mockTime('2026-10-18 13:00:00');

        $text = $replace('second', 2);

        self::assertEquals(new \DateTimeImmutable('2026-10-18 13:00:00'), $text->getUpdatedAt());
        self::assertSame(['content' => 'second', 'version' => 3, 'updated_at' => '2026-10-18 13:00:00'], $this->storedRow());
    }

    public function testChecksTheVersionBeforeComparingTheContent(): void
    {
        $replace = $this->service(ReplaceText::class);
        $replace('same', 1);

        $this->expectException(TextVersionConflict::class);
        $replace('same', 1);
    }

    public function testRejectsAWriteRacingAConcurrentCommit(): void
    {
        $this->service(TextRepository::class)->get();
        $this->entityManager()->getConnection()->executeStatement("UPDATE text SET content = 'other', version = version + 1");

        $this->expectException(OptimisticLockException::class);
        $this->service(ReplaceText::class)('mine', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRow(): array
    {
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT content, version, updated_at FROM text WHERE id = 1');
        self::assertIsArray($row);

        return $row;
    }
}

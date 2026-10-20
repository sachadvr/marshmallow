<?php

declare(strict_types=1);

namespace App\Tests\Unit\Realtime;

use App\Realtime\BestEffortHub;
use App\Realtime\Topics;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class BestEffortHubTest extends TestCase
{
    public function testDelegatesASuccessfulPublication(): void
    {
        $hub = new BestEffortHub($this->hub(static fn (): string => 'urn:uuid:1'), $this->logger());

        self::assertSame('urn:uuid:1', $hub->publish(new Update(Topics::TEXT, '{}')));
        self::assertSame('/.well-known/mercure', $hub->getPublicUrl());
    }

    public function testSwallowsAndLogsAFailingPublication(): void
    {
        $failure = new \RuntimeException('hub down');
        $logger = $this->logger();
        $hub = new BestEffortHub($this->hub(static fn (): never => throw $failure), $logger);

        self::assertSame('', $hub->publish(new Update(Topics::FILES, '{}')));
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame([Topics::FILES], $logger->records[0]['context']['topics']);
        self::assertSame($failure, $logger->records[0]['context']['exception']);
    }

    public function testSwallowsErrorsToo(): void
    {
        $hub = new BestEffortHub($this->hub(static fn (): never => throw new \Error('Call to undefined function mercure_publish()')), $this->logger());

        self::assertSame('', $hub->publish(new Update(Topics::TEXT, '{}')));
    }

    private function hub(callable $publisher): MockHub
    {
        return new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('x'), $publisher, publicUrl: '/.well-known/mercure');
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string|\Stringable, context: array<mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Mercure\Update;

final class UpdateRecorder
{
    /** @var list<Update> */
    private array $updates = [];

    public function __invoke(Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:'.\count($this->updates);
    }

    /**
     * @return list<Update>
     */
    public function updates(): array
    {
        return $this->updates;
    }

    /**
     * @return list<Update>
     */
    public function updatesFor(string $topic): array
    {
        return array_values(array_filter(
            $this->updates,
            static fn (Update $update): bool => \in_array($topic, $update->getTopics(), true),
        ));
    }
}

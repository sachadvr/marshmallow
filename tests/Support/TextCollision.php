<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\Event\OnFlushEventArgs;

final class TextCollision
{
    private bool $armed = false;

    public function arm(): void
    {
        $this->armed = true;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->armed) {
            return;
        }
        $this->armed = false;
        $args->getObjectManager()->getConnection()->executeStatement('UPDATE text SET version = version + 1');
    }
}

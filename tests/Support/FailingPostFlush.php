<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class FailingPostFlush
{
    private bool $armed = false;

    public function arm(): void
    {
        $this->armed = true;
    }

    public function postFlush(): void
    {
        if (!$this->armed) {
            return;
        }
        $this->armed = false;

        throw new \RuntimeException('postFlush failed');
    }
}

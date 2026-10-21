<?php

declare(strict_types=1);

function withElse(bool $flag): string
{
    if ($flag) {
        return 'yes';
    } else {
        return 'no';
    }
}

function withElseif(int $value): string
{
    if (0 === $value) {
        return 'zero';
    } elseif ($value > 0) {
        return 'positive';
    }

    return 'negative';
}

function withGuardClause(bool $flag): string
{
    if ($flag) {
        return 'yes';
    }

    return 'no';
}

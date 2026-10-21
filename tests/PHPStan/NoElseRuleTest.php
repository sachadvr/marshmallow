<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoElseRule>
 */
final class NoElseRuleTest extends RuleTestCase
{
    public function testElseAndElseifAreReportedButGuardClausesAreNot(): void
    {
        $message = 'Avoid else/elseif: use an early return, a guard clause or a ternary.';

        $this->analyse([__DIR__.'/data/else.php'], [
            [$message, 7],
            [$message, 16],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoElseRule();
    }
}

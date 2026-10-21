<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<If_>
 */
final class NoElseRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (null === $node->else && [] === $node->elseifs) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Avoid else/elseif: use an early return, a guard clause or a ternary.')
                ->identifier('app.noElse')
                ->build(),
        ];
    }
}

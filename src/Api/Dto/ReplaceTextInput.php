<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Entity\Text;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Length;

final class ReplaceTextInput
{
    #[Assert\NotNull]
    #[Length(max: Text::MAX_BYTES, countUnit: Length::COUNT_BYTES)]
    public ?string $content = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $baseVersion = null;
}

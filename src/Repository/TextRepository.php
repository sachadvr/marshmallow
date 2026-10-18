<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Text;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Text>
 */
final class TextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Text::class);
    }

    public function get(): Text
    {
        return $this->find(Text::SINGLETON_ID)
            ?? throw new \LogicException('The text singleton row is missing: run the migrations.');
    }
}

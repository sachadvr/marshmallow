<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

abstract class IntegrationTestCase extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const array ISOLATED_ENV = ['DATABASE_URL', 'FILES_DIR'];

    protected string $filesDir;
    private string $workDir;
    /** @var array<string, mixed> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/marshmallow-it-'.bin2hex(random_bytes(6));
        $this->filesDir = $this->workDir.'/files';
        new Filesystem()->mkdir($this->workDir);

        $this->overrideEnv([
            'DATABASE_URL' => 'sqlite:///'.$this->workDir.'/test.db',
            'FILES_DIR' => $this->filesDir,
        ]);
        self::mockTime('2026-10-18 12:00:00');
        self::bootKernel();
        $this->migrate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreEnv();
        new Filesystem()->remove($this->workDir);
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }

    private function migrate(): void
    {
        \assert(null !== self::$kernel);
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]), $output);

        if (0 !== $exitCode) {
            throw new \RuntimeException('Migrations failed: '.$output->fetch());
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function overrideEnv(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->previousEnv[$name] = $_ENV[$name] ?? null;
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
    }

    private function restoreEnv(): void
    {
        foreach (self::ISOLATED_ENV as $name) {
            $previous = $this->previousEnv[$name] ?? null;

            if (!\is_string($previous)) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);

                continue;
            }
            $_ENV[$name] = $_SERVER[$name] = $previous;
            putenv($name.'='.$previous);
        }
    }
}

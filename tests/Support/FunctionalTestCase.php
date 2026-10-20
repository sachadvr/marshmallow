<?php

declare(strict_types=1);

namespace App\Tests\Support;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use App\Entity\Text;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Update;

abstract class FunctionalTestCase extends ApiTestCase
{
    private const array OVERRIDDEN_ENV = ['DATABASE_URL', 'FILES_DIR', 'TRUSTED_HOSTS'];

    protected Client $client;
    protected string $workDir;

    /** @var array<string, array{env: mixed, server: mixed}> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/marshmallow-functional-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir([$this->workDir.'/files', $this->workDir.'/uploads']);
        $this->overrideEnv([
            'DATABASE_URL' => 'sqlite:///'.$this->workDir.'/test.db',
            'FILES_DIR' => $this->workDir.'/files',
            'TRUSTED_HOSTS' => '^localhost$',
        ]);

        $this->client = static::createClient(defaultOptions: ['headers' => ['Accept' => 'application/json']]);
        $this->client->disableReboot();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Request::setTrustedHosts([]);

        foreach ($this->previousEnv as $name => $previous) {
            $this->restore($_ENV, $name, $previous['env']);
            $this->restore($_SERVER, $name, $previous['server']);
        }
        new Filesystem()->remove($this->workDir);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }

    /**
     * @return list<Update>
     */
    protected function updatesFor(string $topic): array
    {
        return $this->service(UpdateRecorder::class)->updatesFor($topic);
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
        $service = static::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    protected function uploadedFile(string $name, string $content, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam($this->workDir.'/uploads', 'upload');
        self::assertIsString($path);
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, $error, true);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    protected function upload(UploadedFile $file, array $headers = []): array
    {
        return [
            'headers' => ['Content-Type' => 'multipart/form-data'] + $headers,
            'extra' => ['files' => ['file' => $file]],
        ];
    }

    /**
     * @param array<string, string> $values
     */
    private function overrideEnv(array $values): void
    {
        foreach (self::OVERRIDDEN_ENV as $name) {
            $this->previousEnv[$name] = ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $values[$name];
        }
    }

    /**
     * @param array<mixed> $globals
     */
    private function restore(array &$globals, string $name, mixed $previous): void
    {
        if (null === $previous) {
            unset($globals[$name]);

            return;
        }

        $globals[$name] = $previous;
    }

    private function createSchema(): void
    {
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $entityManager->getConnection()->insert('text', [
            'id' => Text::SINGLETON_ID,
            'content' => '',
            'version' => 1,
            'updated_at' => '2026-10-18 00:00:00',
        ]);
    }
}

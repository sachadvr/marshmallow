<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\GetFile;
use App\Http\DownloadHeaders;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final class DownloadFileController
{
    public const string ROUTE = 'app_file_content';

    #[Route('/api/files/{id}/content', name: self::ROUTE, requirements: ['id' => Requirement::UUID], methods: ['GET'], format: 'json')]
    public function __invoke(string $id, GetFile $get, DownloadHeaders $headers): BinaryFileResponse
    {
        $download = $get($id);

        return new BinaryFileResponse($download->path, headers: $headers->for($download->file), public: false);
    }
}

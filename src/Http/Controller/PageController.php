<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Page\PageProps;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public function __construct(
        private readonly PageProps $props,
        #[Autowire(param: 'app.page_csp')]
        private readonly ?string $contentSecurityPolicy,
    ) {
    }

    #[Route('/', name: 'app_page', methods: ['GET'])]
    public function __invoke(): Response
    {
        $response = $this->render('page.html.twig', ['props' => $this->props->build()]);

        if (null !== $this->contentSecurityPolicy) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy);
        }

        return $response;
    }
}

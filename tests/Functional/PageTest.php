<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\File;
use App\Entity\Text;
use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class PageTest extends FunctionalTestCase
{
    private const string CSP = "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' blob: data:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";

    public function testRendersTheDocumentThatMountsTheVueRoot(): void
    {
        $page = $this->page('/');

        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertResponseHeaderSame('Content-Security-Policy', self::CSP);
        self::assertSame('fr', $page->filter('html')->attr('lang'));
        self::assertSame('Marshmallow', $page->filter('title')->text());
        self::assertSame('/favicon.svg', $page->filter('link[rel="icon"][type="image/svg+xml"]')->attr('href'));
        self::assertCount(1, $page->filter('link[rel="stylesheet"][href^="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans"]'));
        self::assertCount(1, $page->filter('script[type="module"][src="/build/assets/app-test.js"]'));
        self::assertCount(1, $page->filter('link[rel="stylesheet"][href="/build/assets/app-test.css"]'));

        $root = $page->filter('#app');
        self::assertSame('symfony--ux-vue--vue', $root->attr('data-controller'));
        self::assertSame('App', $root->attr('data-symfony--ux-vue--vue-component-value'));
    }

    public function testPropsCarryTheApiStateAndTheLimits(): void
    {
        $props = $this->props();

        self::assertSame(['initialText', 'initialLatestFile', 'limits'], array_keys($props));
        self::assertSame($this->apiJson('/api/text'), $props['initialText']);
        self::assertSame('', $this->field($props, 'initialText', 'content'));
        self::assertSame(1, $this->field($props, 'initialText', 'version'));
        self::assertNull($props['initialLatestFile']);
        self::assertSame(['maxUploadBytes' => File::MAX_BYTES, 'maxTextBytes' => Text::MAX_BYTES], $props['limits']);
    }

    public function testPropsFollowTheLastWrites(): void
    {
        $this->client->request('PUT', '/api/text', ['json' => ['content' => 'héllo <b>"x"</b>', 'baseVersion' => 1]]);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/api/files', $this->upload($this->uploadedFile('notes.txt', 'hello')));
        self::assertResponseStatusCodeSame(201);

        $props = $this->props();

        self::assertSame($this->apiJson('/api/text'), $props['initialText']);
        self::assertSame('héllo <b>"x"</b>', $this->field($props, 'initialText', 'content'));
        self::assertSame([$props['initialLatestFile']], $this->apiJson('/api/files/latest'));
        self::assertSame('notes.txt', $this->field($props, 'initialLatestFile', 'name'));
    }

    private function page(string $path): Crawler
    {
        $response = $this->client->request('GET', $path, ['headers' => ['Accept' => 'text/html']]);
        self::assertResponseIsSuccessful();

        return new Crawler($response->getContent());
    }

    /**
     * @return array<mixed>
     */
    private function props(): array
    {
        $json = $this->page('/')->filter('#app')->attr('data-symfony--ux-vue--vue-props-value');
        self::assertIsString($json);
        $props = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($props);

        return $props;
    }

    /**
     * @return array<mixed>
     */
    private function apiJson(string $path): array
    {
        return $this->client->request('GET', $path)->toArray();
    }

    /**
     * @param array<mixed> $props
     */
    private function field(array $props, string $prop, string $field): mixed
    {
        $value = $props[$prop] ?? null;
        self::assertIsArray($value);

        return $value[$field] ?? null;
    }
}

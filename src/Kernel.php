<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\DownloadPolicyPass;
use App\DependencyInjection\FileStorageFactoryPass;
use App\DependencyInjection\ViteEntrypointsPerRenderPass;
use App\Http\Download\AsDownloadPolicy;
use App\Http\Download\DownloadPolicy;
use App\Storage\AsFileStorageFactory;
use App\Storage\FileStorageFactory;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(AsDownloadPolicy::class, static function (ChildDefinition $definition, AsDownloadPolicy $attribute): void {
            $definition->addTag(DownloadPolicy::TAG, ['priority' => $attribute->priority, 'fallback' => $attribute->fallback]);
        });
        $container->registerAttributeForAutoconfiguration(AsFileStorageFactory::class, static function (ChildDefinition $definition, AsFileStorageFactory $attribute): void {
            $definition->addTag(FileStorageFactory::TAG, ['scheme' => $attribute->scheme]);
        });

        $container->addCompilerPass(new DownloadPolicyPass());
        $container->addCompilerPass(new FileStorageFactoryPass());

        if ('dev' === $this->environment) {
            $container->addCompilerPass(new ViteEntrypointsPerRenderPass());
        }
    }

    /**
     * @return list<string>
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}

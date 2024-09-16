<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Laravel\Controller;

use ApiPlatform\Documentation\Entrypoint;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\OpenApi\Serializer\LegacyOpenApiNormalizer;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates the API entrypoint.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class EntrypointController
{
    private static ResourceNameCollection $resourceNameCollection;

    /**
     * @param array<string, string[]>             $documentationFormats
     * @param ProviderInterface<object>           $provider
     * @param ProcessorInterface<mixed, Response> $processor
     */
    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ProviderInterface $provider,
        private readonly ProcessorInterface $processor,
        private readonly array $documentationFormats = [],
    ) {
    }

    public function __invoke(Request $request): Response
    {
        self::$resourceNameCollection = $this->resourceNameCollectionFactory->create();
        $context = [
            'request' => $request,
            'spec_version' => (string) $request->query->get(LegacyOpenApiNormalizer::SPEC_VERSION),
        ];
        $request->attributes->set('_api_platform_disable_listeners', true);
        $operation = new Get(
            outputFormats: $this->documentationFormats,
            read: true,
            serialize: true,
            class: Entrypoint::class,
            provider: [self::class, 'provide']
        );
        $request->attributes->set('_api_operation', $operation);
        $body = $this->provider->provide($operation, [], $context);
        $operation = $request->attributes->get('_api_operation');

        return $this->processor->process($body, $operation, [], $context);
    }

    public static function provide(): Entrypoint
    {
        return new Entrypoint(self::$resourceNameCollection);
    }
}
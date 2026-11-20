<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ConsentManager\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ConsentManager\Api\State\ConsentConfigurationProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What a headless front needs to build the same banner as the theme: the current
 * policy version, the categories in the request's language and the services each covers.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/consent-configuration',
            openapi: new OpenApiOperation(
                summary: 'Consent categories and services',
                description: 'The categories, in display order, with the services and cookies each covers. A choice stored with another policy version must be asked again.',
            ),
            normalizationContext: ['groups' => [self::GROUP_READ]],
            provider: ConsentConfigurationProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final class ConsentConfiguration
{
    public const GROUP_READ = 'consent_configuration:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ])]
    public int $policy = 0;

    #[Groups([self::GROUP_READ])]
    public string $cookieName = '';

    #[Groups([self::GROUP_READ])]
    public int $lifetimeDays = 0;

    /** @var list<array{code: string, title: string, description: string, required: bool, services: list<array<string, mixed>>}> */
    #[Groups([self::GROUP_READ])]
    public array $categories = [];
}

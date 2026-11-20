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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ConsentManager\Api\State\ConsentChoiceProcessor;
use ConsentManager\Api\State\ConsentChoiceProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A visitor's choice, as a headless front records and reads it. The same rules apply
 * as on the theme's banner: unknown categories and refused necessary ones are rejected.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/consents',
            openapi: new OpenApiOperation(
                summary: 'Record a consent choice',
                description: 'Writes a proof of the choice and returns the value of the consent cookie to store. Send back the visitorId of an earlier answer when the visitor changes their mind. A category not listed is refused.',
            ),
            normalizationContext: ['groups' => [self::GROUP_READ]],
            denormalizationContext: ['groups' => [self::GROUP_WRITE]],
            processor: ConsentChoiceProcessor::class,
        ),
        new Get(
            uriTemplate: '/front/consents/{visitorId}',
            openapi: new OpenApiOperation(
                summary: 'Read the latest consent choice of a visitor',
                description: 'current is false when the policy changed since the choice: the visitor has to be asked again.',
            ),
            normalizationContext: ['groups' => [self::GROUP_READ]],
            provider: ConsentChoiceProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final class ConsentChoice
{
    public const GROUP_READ = 'consent_choice:read';
    public const GROUP_WRITE = 'consent_choice:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\Uuid(versions: [Assert\Uuid::V4_RANDOM])]
    public ?string $visitorId = null;

    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\Positive]
    public ?int $policy = null;

    /** @var list<string> */
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\All([new Assert\Type('string')])]
    public array $accepted = [];

    /** @var list<string> */
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\All([new Assert\Type('string')])]
    public array $refused = [];

    #[Groups([self::GROUP_READ])]
    public ?\DateTimeImmutable $recordedAt = null;

    #[Groups([self::GROUP_READ])]
    public bool $current = false;

    /** Value of the consent cookie, for a front that stores the choice itself. */
    #[Groups([self::GROUP_READ])]
    public ?string $cookie = null;

    #[Groups([self::GROUP_READ])]
    public ?int $maxAge = null;
}

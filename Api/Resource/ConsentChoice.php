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
 * The choice of the current visitor, as a headless front records and reads it. The
 * visitor is the one named by the consent cookie, or by the X-Thelia-Consent header
 * carrying the same value: nobody reads or extends the choice of somebody else.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/front/consent',
            openapi: new OpenApiOperation(
                summary: 'Record the consent choice of the current visitor',
                description: 'Writes a proof of the choice and returns the value of the consent cookie to store. A visitor who already chose is known by the thelia_consent cookie or the X-Thelia-Consent header and keeps their identifier; anyone else gets a new one. A category not listed is refused; refusing a strictly necessary one, or naming an unknown one, is a 422.',
            ),
            normalizationContext: ['groups' => [self::GROUP_READ]],
            denormalizationContext: ['groups' => [self::GROUP_WRITE]],
            processor: ConsentChoiceProcessor::class,
        ),
        new Get(
            uriTemplate: '/front/consent',
            openapi: new OpenApiOperation(
                summary: 'Read the latest consent choice of the current visitor',
                description: 'The visitor is the one of the thelia_consent cookie or the X-Thelia-Consent header; 404 without one. current is false once the policy changed: the visitor has to be asked again.',
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
    #[Groups([self::GROUP_READ])]
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

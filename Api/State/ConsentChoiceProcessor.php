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

namespace ConsentManager\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ConsentManager\Api\Resource\ConsentChoice;
use ConsentManager\ConsentManager;
use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Service\ConsentChoiceMapper;
use ConsentManager\Service\ConsentCookie;
use ConsentManager\Service\ConsentRecorder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ConsentChoice, ConsentChoice>
 */
final readonly class ConsentChoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private ConsentRecorder $recorder,
        private ConsentChoiceMapper $mapper,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.'.ConsentManager::RATE_LIMITER)]
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConsentChoice
    {
        $request = $this->requestStack->getCurrentRequest();
        $client = $request?->getClientIp() ?? 'unknown';
        $limit = $this->limiter->create($client)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Too many consent choices from this address.');
        }

        try {
            // The visitor is the one the request already carries, never one named in the body.
            $record = $this->recorder->record(ConsentCookie::fromRequest($request)?->visitorId, $data->policy, $data->accepted, $data->refused);
        } catch (InvalidConsentChoiceException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->mapper->toResource($record);
    }
}

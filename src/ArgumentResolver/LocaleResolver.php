<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\ArgumentResolver;

use Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use IWD\SymfonyDoctrineSearch\Dto\Input\Locale;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\LocaleMakerInterface;

class LocaleResolver implements ValueResolverInterface
{
    public function __construct(
        private LocaleMakerInterface $localeMaker
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        if (!$this->supports($request, $argument)) {
            return [];
        }

        yield $this->localeMaker::make($request);
    }

    private function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return Locale::class === $argument->getType();
    }
}

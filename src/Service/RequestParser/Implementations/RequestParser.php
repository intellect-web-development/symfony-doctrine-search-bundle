<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service\RequestParser\Implementations;

use Symfony\Component\HttpFoundation\Request;
use IWD\SymfonyDoctrineSearch\Service\RequestParser\Interfaces\RequestParserInterface;

class RequestParser implements RequestParserInterface
{
    /**
     * @return array<string, string>
     *
     * @throws \JsonException
     */
    public function parse(Request $request): array
    {
        $content = json_decode((string) $request->getContent(), true, 512);
        if (null === $content) {
            $content = [];
        }

        /** @var array<string, string> $payload */
        $payload = array_merge(
            $request->query->all(),
            $content,
            $request->request->all()
        );

        return $payload;
    }
}

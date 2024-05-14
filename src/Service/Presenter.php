<?php

declare(strict_types=1);

namespace IWD\SymfonyDoctrineSearch\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use IWD\SymfonyDoctrineSearch\Dto\Input\OutputFormat;

class Presenter
{
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function present(
        mixed $data,
        array $headers = [],
        OutputFormat $outputFormat = null,
        int $status = 200,
    ): Response {
        if (null === $outputFormat) {
            $outputFormat = new OutputFormat('json');
        }

        $content = $this->serializer->serialize(
            $data,
            $outputFormat->getFormat()
        );

        $response = new Response(
            content: $content,
            status: $status
        );

        $headers = array_merge(
            $headers,
            ['Content-Type' => 'application/' . $outputFormat->getFormat()]
        );
        $response->headers->add($headers);

        return $response;
    }
}
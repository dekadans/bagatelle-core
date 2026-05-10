<?php

namespace tthe\Bagatelle\Error;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Representation of the format described in RFC 9457, "Problem Details for HTTP APIs".
 */
class Problem implements \JsonSerializable
{
    public function __construct(
        public string $type = 'about:blank',
        public ?int $status = null,
        public string $title = 'An unidentified error has occurred.',
        public ?string $detail = null,
        public ?string $instance = null,
        public array $extensions = []
    ) {}

    public function jsonSerialize(): array
    {
        $base = [
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->title,
            'detail' => $this->detail,
            'instance' => $this->instance,
        ];

        $cleanExtensions = array_filter(
            $this->extensions,
            fn($key) => !array_key_exists($key, $base),
            ARRAY_FILTER_USE_KEY
        );

        return array_merge(array_filter($base), $cleanExtensions);
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse($this, headers: [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}

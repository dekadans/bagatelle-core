<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Error;

use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Conversion of exception information into a Problem Details structure.
 */
class ExceptionProblem extends Problem
{
    public function __construct(FlattenException $exception, bool $debug = false)
    {
        parent::__construct(
            status: $exception->getStatusCode(),
            title: $exception->getStatusText(),
            detail: $this->getDetail($exception, $debug),
            extensions: $debug ? $this->getExceptionArray($exception) : []
        );
    }

    private function getDetail(FlattenException $exception, bool $debug): string
    {
        return $exception->getStatusCode() < 500 || $debug
            ? $exception->getMessage()
            : 'An error occurred when processing the request.';
    }

    private function getExceptionArray(FlattenException $exception): array
    {
        $data = [];
        foreach ($exception->toArray() as $ex) {
            $filtered = array_filter(
                $ex,
                fn($key) => in_array($key, ['class', 'trace', 'message']),
                ARRAY_FILTER_USE_KEY
            );
            $filtered['trace'] = array_map(
                function ($tr) {
                    return [
                        'file' => $tr['file'],
                        'line' => $tr['line'],
                        'function' => $tr['class'] . $tr['type'] . $tr['function'] . ($tr['function'] ? '()' : ''),
                    ];
                },
                $filtered['trace']
            );
            $data[] = $filtered;
        }

        return ['exceptions' => $data];
    }
}
<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP Methods as backed enum values.
 */
enum Method: string
{
    case GET = Request::METHOD_GET;
    case POST = Request::METHOD_POST;
    case PUT = Request::METHOD_PUT;
    case PATCH = Request::METHOD_PATCH;
    case DELETE = Request::METHOD_DELETE;
    case OPTIONS = Request::METHOD_OPTIONS;
    case QUERY = Request::METHOD_QUERY;
    case CONNECT = Request::METHOD_CONNECT;
    case TRACE = Request::METHOD_TRACE;
    case HEAD = Request::METHOD_HEAD;
}

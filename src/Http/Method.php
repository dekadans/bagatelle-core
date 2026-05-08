<?php

namespace tthe\Bagatelle\Http;

enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case QUERY = 'QUERY';
    case CONNECT = 'CONNECT';
    case TRACE = 'TRACE';
    case HEAD = 'HEAD';
}

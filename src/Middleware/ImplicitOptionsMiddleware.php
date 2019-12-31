<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Laminas\Diactoros\Response;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handle implicit OPTIONS requests.
 *
 * Place this middleware after the routing middleware so that it can handle
 * implicit OPTIONS requests -- requests where OPTIONS is used, but the route
 * does not explicitly handle that request method.
 *
 * When invoked, it will create a response with status code 200 and an Allow
 * header that defines all accepted request methods.
 *
 * You may optionally pass a response prototype to the constructor; when
 * present, that prototype will be used to create a new response with the
 * Allow header.
 *
 * The middleware is only invoked in these specific conditions:
 *
 * - an OPTIONS request
 * - with a `RouteResult` present
 * - where the `RouteResult` contains a `Route` instance
 * - and the `Route` instance defines implicit OPTIONS.
 *
 * In all other circumstances, it will return the result of the delegate.
 */
class ImplicitOptionsMiddleware implements MiddlewareInterface
{
    /**
     * @var null|ResponseInterface
     */
    private $response;

    /**
     * @param null|ResponseInterface $response Response prototype to use for
     *     implicit OPTIONS requests; if not provided a laminas-diactoros Response
     *     instance will be created and used.
     */
    public function __construct(ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    /**
     * Handle an implicit OPTIONS request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if ($request->getMethod() !== RequestMethod::METHOD_OPTIONS) {
            return $handler->handle($request);
        }

        if (false === ($result = $request->getAttribute(RouteResult::class, false))) {
            return $handler->handle($request);
        }

        $route = $result->getMatchedRoute();
        if (! $route || ! $route->implicitOptions()) {
            return $handler->handle($request);
        }

        $methods = implode(',', $route->getAllowedMethods());
        return $this->getResponse()->withHeader('Allow', $methods);
    }

    /**
     * Return the response prototype to use for an implicit OPTIONS request.
     */
    private function getResponse() : ResponseInterface
    {
        return $this->response ?: new Response('php://temp', StatusCode::STATUS_OK);
    }
}
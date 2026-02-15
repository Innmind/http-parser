<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    ServerRequest,
    ServerRequest\Cookies,
    Header\Cookie,
    Header\Parameter,
};
use Innmind\Immutable\Map;

final class DecodeCookie
{
    private function __construct()
    {
    }

    public function __invoke(ServerRequest $request): ServerRequest
    {
        return $request
            ->headers()
            ->find(Cookie::class)
            ->map(static fn($cookie) => $cookie->parameters())
            ->match(
                fn($parameters) => $this->decode($parameters, $request),
                static fn() => $request,
            );
    }

    public static function of(): self
    {
        return new self;
    }

    /**
     * @param Map<string, Parameter> $parameters
     */
    private function decode(
        Map $parameters,
        ServerRequest $request,
    ): ServerRequest {
        return ServerRequest::of(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            $request->body(),
            Cookies::of($parameters->map(
                static fn($_, $parameter) => $parameter->value(),
            )),
            $request->query(),
            $request->form(),
            $request->files(),
        );
    }
}

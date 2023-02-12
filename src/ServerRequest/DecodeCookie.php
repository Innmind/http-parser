<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    Message\ServerRequest,
    Message\Cookies,
    Header\Cookie,
    Header\CookieValue,
};
use Innmind\Immutable\Predicate\Instance;

final class DecodeCookie
{
    public function __invoke(ServerRequest $request): ServerRequest
    {
        return $request
            ->headers()
            ->find(Cookie::class)
            ->flatMap(static fn($header) => $header->values()->find(static fn() => true))
            ->keep(Instance::of(CookieValue::class))
            ->match(
                fn($cookie) => $this->decode($cookie, $request),
                static fn() => $request,
            );
    }

    private function decode(
        CookieValue $cookie,
        ServerRequest $request,
    ): ServerRequest {
        return new ServerRequest\ServerRequest(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            $request->body(),
            $request->environment(),
            new Cookies($cookie->parameters()->map(
                static fn($_, $parameter) => $parameter->value(),
            )),
            $request->query(),
            $request->form(),
            $request->files(),
        );
    }
}

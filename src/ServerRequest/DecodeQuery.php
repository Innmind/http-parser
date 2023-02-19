<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    Message\ServerRequest,
    Message,
};
use Innmind\Url\Query;

final class DecodeQuery
{
    private function __construct()
    {
    }

    public function __invoke(ServerRequest $request): ServerRequest
    {
        if ($request->url()->query()->equals(Query::none())) {
            return $request;
        }

        \parse_str($request->url()->query()->toString(), $query);

        return new ServerRequest\ServerRequest(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            $request->body(),
            $request->environment(),
            $request->cookies(),
            Message\Query::of($query),
            $request->form(),
            $request->files(),
        );
    }

    public static function of(): self
    {
        return new self;
    }
}

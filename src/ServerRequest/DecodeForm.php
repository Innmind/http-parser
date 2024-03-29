<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    ServerRequest,
    ServerRequest\Form,
    Header\ContentType,
    Header\ContentTypeValue,
};
use Innmind\Filesystem\File\Content;

final class DecodeForm
{
    private function __construct()
    {
    }

    public function __invoke(ServerRequest $request): ServerRequest
    {
        return $request
            ->headers()
            ->find(ContentType::class)
            ->map(static fn($header) => $header->content())
            ->match(
                fn($value) => $this->decode($value, $request),
                static fn() => $request,
            );
    }

    public static function of(): self
    {
        return new self;
    }

    private function decode(
        ContentTypeValue $header,
        ServerRequest $request,
    ): ServerRequest {
        if ($header->type() !== 'application' || $header->subType() !== 'x-www-form-urlencoded') {
            return $request;
        }

        \parse_str($request->body()->toString(), $post);

        return ServerRequest::of(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            Content::none(),
            $request->environment(),
            $request->cookies(),
            $request->query(),
            Form::of($post),
            $request->files(),
        );
    }
}

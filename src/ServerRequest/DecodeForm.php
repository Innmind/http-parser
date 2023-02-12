<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    Message\ServerRequest,
    Message\Form,
    Header\ContentType,
    Header\ContentTypeValue,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\Predicate\Instance;

final class DecodeForm
{
    public function __invoke(ServerRequest $request): ServerRequest
    {
        return $request
            ->headers()
            ->find(ContentType::class)
            ->flatMap(static fn($header) => $header->values()->find(static fn() => true))
            ->keep(Instance::of(ContentTypeValue::class))
            ->match(
                fn($value) => $this->decode($value, $request),
                static fn() => $request,
            );
    }

    private function decode(
        ContentTypeValue $header,
        ServerRequest $request,
    ): ServerRequest {
        if ($header->type() !== 'application' || $header->subType() !== 'x-www-form-urlencoded') {
            return $request;
        }

        \parse_str($request->body()->toString(), $post);

        return new ServerRequest\ServerRequest(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            Content\None::of(),
            $request->environment(),
            $request->cookies(),
            $request->query(),
            Form::of($post),
            $request->files(),
        );
    }
}

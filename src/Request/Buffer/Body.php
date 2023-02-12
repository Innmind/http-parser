<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
    Headers,
    Header\ContentLength,
};
use Innmind\Filesystem\File\Content;
use Innmind\Url\Url;
use Innmind\Immutable\{
    Maybe,
    Str,
};

final class Body implements State
{
    private Method $method;
    private Url $url;
    private ProtocolVersion $protocol;
    private Headers $headers;
    /** @var Maybe<0|positive-int> */
    private Maybe $length;
    private Str $body;

    /**
     * @param Maybe<0|positive-int> $length
     */
    private function __construct(
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Headers $headers,
        Maybe $length,
        Str $body,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->protocol = $protocol;
        $this->headers = $headers;
        $this->length = $length;
        $this->body = $body;
    }

    public static function new(
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Headers $headers,
    ): self {
        /** @var Maybe<0|positive-int> */
        $length = $headers
            ->find(ContentLength::class)
            ->flatMap(static fn($header) => $header->values()->find(static fn() => true)) // first
            ->map(static fn($length) => (int) $length->toString()) // at this moment the header doesn't expose directly the int
            ->filter(static fn($length) => $length >= 0);

        return new self(
            $method,
            $url,
            $protocol,
            $headers,
            $length,
            Str::of(''),
        );
    }

    public function add(Str $chunk): self
    {
        $body = $this->body->append($chunk->toString());
        $body = $this->length->match(
            static fn($length) => $body->take($length),
            static fn() => $body,
        );

        return new self(
            $this->method,
            $this->url,
            $this->protocol,
            $this->headers,
            $this->length,
            $body,
        );
    }

    public function finish(): Maybe
    {
        /** @var Maybe<Request> */
        return Maybe::just(new Request\Request(
            $this->url,
            $this->method,
            $this->protocol,
            $this->headers,
            Content\Lines::ofContent($this->body->toString()),
        ));
    }
}

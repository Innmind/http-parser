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
use Innmind\Stream\{
    Capabilities,
    Bidirectional,
};
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
    private Bidirectional $body;
    /** @var 0|positive-int */
    private int $accumulated;

    /**
     * @param Maybe<0|positive-int> $length
     * @param 0|positive-int $accumulated
     */
    private function __construct(
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Headers $headers,
        Maybe $length,
        Bidirectional $body,
        int $accumulated,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->protocol = $protocol;
        $this->headers = $headers;
        $this->length = $length;
        $this->body = $body;
        $this->accumulated = $accumulated;
    }

    public static function new(
        Capabilities $capabilities,
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
            $capabilities->temporary()->new(),
            0,
        );
    }

    public function add(Str $chunk): State
    {
        $chunk = $chunk->toEncoding('ASCII');
        $toWrite = $this
            ->length
            ->map(fn($length) => \max(0, $length - $this->accumulated))
            ->match(
                static fn($length) => $chunk->take($length),
                static fn() => $chunk,
            );

        /** @psalm-suppress ArgumentTypeCoercion Due to write returning a Writable */
        return $this
            ->body
            ->write($toWrite)
            ->map(fn(Bidirectional $body) => new self(
                $this->method,
                $this->url,
                $this->protocol,
                $this->headers,
                $this->length,
                $body,
                $this->accumulated + $toWrite->length(),
            ))
            ->match(
                static fn($self) => $self,
                static fn() => new Failure, // failed to write to body
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
            Content\OfStream::of($this->body),
        ));
    }
}

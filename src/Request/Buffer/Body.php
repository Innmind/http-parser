<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
    Headers,
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
    private Str $body;

    private function __construct(
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Headers $headers,
        Str $body,
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->protocol = $protocol;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function new(
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Headers $headers,
    ): self {
        return new self($method, $url, $protocol, $headers, Str::of(''));
    }

    public function add(Str $chunk): self
    {
        return new self(
            $this->method,
            $this->url,
            $this->protocol,
            $this->headers,
            $this->body->append($chunk->toString()),
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

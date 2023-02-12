<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
    Header,
    Headers as Container,
    Factory\Header\TryFactory,
};
use Innmind\Stream\Capabilities;
use Innmind\Url\Url;
use Innmind\Immutable\{
    Maybe,
    Str,
    Sequence,
    Map,
};

final class Headers implements State
{
    private TryFactory $factory;
    private Capabilities $capabilities;
    private Method $method;
    private Url $url;
    private ProtocolVersion $protocol;
    private Str $buffer;
    /** @var Sequence<Str> */
    private Sequence $headers;

    /**
     * @param Sequence<Str> $headers
     */
    private function __construct(
        TryFactory $factory,
        Capabilities $capabilities,
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
        Str $buffer,
        Sequence $headers,
    ) {
        $this->factory = $factory;
        $this->capabilities = $capabilities;
        $this->method = $method;
        $this->url = $url;
        $this->protocol = $protocol;
        $this->buffer = $buffer;
        $this->headers = $headers;
    }

    public static function new(
        TryFactory $factory,
        Capabilities $capabilities,
        Method $method,
        Url $url,
        ProtocolVersion $protocol,
    ): self {
        return new self(
            $factory,
            $capabilities,
            $method,
            $url,
            $protocol,
            Str::of('', 'ASCII'),
            Sequence::of(),
        );
    }

    public function add(Str $chunk): State
    {
        $buffer = $this->buffer->append($chunk->toString());

        return match ($buffer->contains("\n")) {
            true => $this->parse($buffer),
            false => new self(
                $this->factory,
                $this->capabilities,
                $this->method,
                $this->url,
                $this->protocol,
                $buffer,
                $this->headers,
            ),
        };
    }

    public function finish(): Maybe
    {
        if (!$this->buffer->rightTrim("\r")->empty()) {
            /** @var Maybe<Request> */
            return Maybe::nothing();
        }

        /** @var Maybe<Request> */
        return $this
            ->headers()
            ->map(fn($headers) => new Request\Request(
                $this->url,
                $this->method,
                $this->protocol,
                $headers,
            ));
    }

    private function parse(Str $buffer): State
    {
        // by adding an empty chunk at the end will recursively parse the
        // headers while there is a new line in the buffer
        return $buffer
            ->split("\n")
            ->match(
                fn($header, $rest) => match ($header->rightTrim("\r")->empty()) {
                    true => $this->body(Str::of("\n")->join($rest->map(
                        static fn($part) => $part->toString(),
                    ))),
                    false => new self(
                        $this->factory,
                        $this->capabilities,
                        $this->method,
                        $this->url,
                        $this->protocol,
                        Str::of("\n")->join($rest->map(
                            static fn($part) => $part->toString(),
                        )),
                        ($this->headers)($header),
                    ),
                },
                fn() => $this->body(),
            )
            ->add(Str::of(''));
    }

    /**
     * @return Maybe<Container>
     */
    private function headers(): Maybe
    {
        /**
         * @psalm-suppress NamedArgumentNotAllowed
         * Technically as header name can contain any octet between 0 and 127
         * except control ones, the regexp below is a bit more restrictive than
         * that by only accepting letters, numbers, '-', '_' and '.'
         * @see https://www.rfc-editor.org/rfc/rfc2616#section-4.2
         */
        return $this
            ->headers
            ->map(static fn($header) => $header->rightTrim("\r"))
            ->map(static fn($header) => $header->capture('~^(?<name>[a-zA-Z0-9\-\_\.]+): (?<value>.*)$~'))
            ->map(fn($captured) => $this->createHeader($captured))
            ->match(
                static fn($first, $rest) => Maybe::all($first, ...$rest->toList())->map(
                    static fn(Header ...$headers) => Container::of(...$headers),
                ),
                static fn() => Maybe::just(Container::of()),
            );
    }

    /**
     * @param Map<int|string, Str> $info
     *
     * @return Maybe<Header>
     */
    private function createHeader(Map $info): Maybe
    {
        return Maybe::all($info->get('name'), $info->get('value'))->map(
            fn(Str $name, Str $value) => ($this->factory)($name, $value),
        );
    }

    private function body(Str $buffer = null): State
    {
        $buffer ??= Str::of('');

        return $this
            ->headers()
            ->map(fn($headers) => Body::new(
                $this->capabilities,
                $this->method,
                $this->url,
                $this->protocol,
                $headers,
            ))
            ->match(
                static fn($body) => $body->add($buffer),
                static fn() => new Failure,
            );
    }
}

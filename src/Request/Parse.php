<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Frame\{
    FirstLine,
    Headers,
    Body,
};
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
    Headers as HttpHeaders,
    Factory\Header\TryFactory,
    Factory\Header\Factories,
};
use Innmind\TimeContinuum\Clock;
use Innmind\Filesystem\File\Content;
use Innmind\Url\Url;
use Innmind\IO\{
    Readable\Stream,
    Readable\Frame,
    Sockets\Client,
};
use Innmind\Immutable\{
    Maybe,
    Str,
};

final class Parse
{
    private TryFactory $factory;

    private function __construct(TryFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @return Maybe<Request>
     */
    public function __invoke(Stream|Client $stream): Maybe
    {
        $frame = Frame\Composite::of(
            self::build(...),
            FirstLine::new(),
            Headers::of($this->factory)->flatMap(
                static fn($headers) => Body::of($headers)->map(
                    static fn($content) => [$headers, $content],
                ),
            ),
        );

        return $stream
            ->toEncoding(Str\Encoding::ascii)
            ->frames($frame)
            ->one()
            ->flatMap(static fn($request) => $request);
    }

    public static function of(TryFactory $factory): self
    {
        return new self($factory);
    }

    public static function default(Clock $clock): self
    {
        return new self(new TryFactory(Factories::default($clock)));
    }

    /**
     * @param Maybe<array{Method, Url, ProtocolVersion}> $firstLine
     * @param array{Maybe<HttpHeaders>, Content} $headersAndBody
     *
     * @return Maybe<Request>
     */
    private static function build(
        Maybe $firstLine,
        array $headersAndBody,
    ): Maybe {
        [$headers, $body] = $headersAndBody;

        return Maybe::all($firstLine, $headers)->map(
            static function(...$args) use ($body) {
                /** @var array{array{Method, Url, ProtocolVersion}, HttpHeaders} $args */
                [[$method, $url, $protocol], $headers] = $args;

                return Request::of(
                    $url,
                    $method,
                    $protocol,
                    $headers,
                    $body,
                );
            },
        );
    }
}

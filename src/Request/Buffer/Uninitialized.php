<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
    Factory\Header\TryFactory,
    Factory\Header\Factories,
};
use Innmind\TimeContinuum\Clock;
use Innmind\Url\Url;
use Innmind\Immutable\{
    Maybe,
    Str,
    Predicate\Instance,
};

final class Uninitialized implements State
{
    private TryFactory $factory;
    private Str $header;

    private function __construct(TryFactory $factory, Str $header)
    {
        $this->factory = $factory;
        $this->header = $header;
    }

    public static function new(Clock $clock): self
    {
        return new self(
            new TryFactory(Factories::default($clock)),
            Str::of('', 'ASCII'),
        );
    }

    public function add(Str $chunk): State
    {
        $header = $this->header->append($chunk->toString());

        return match ($header->contains("\n")) {
            false => new self($this->factory, $header),
            true => $this->parse($header),
        };
    }

    public function finish(): Maybe
    {
        /** @var Maybe<Request> */
        return Maybe::nothing();
    }

    private function parse(Str $header): State
    {
        return $header
            ->split("\n")
            ->match(
                fn($header, $rest) => $this
                    ->parseHeader($header)
                    ->match(
                        fn($info) => Headers::new(
                            $this->factory,
                            $info[0],
                            $info[1],
                            $info[2],
                        )->add(
                            Str::of("\n")->join($rest->map(
                                static fn($part) => $part->toString(),
                            )),
                        ),
                        static fn() => new Failure,
                    ),
                static fn() => new Failure,
            );
    }

    /**
     * @return Maybe<array{Method, Url, ProtocolVersion}>
     */
    private function parseHeader(Str $header): Maybe
    {
        $parts = $header->capture('~^(?<method>[A-Z]+) (?<url>.+) HTTP/(?<protocol>1(\.[01])?)$~');
        $method = $parts
            ->get('method')
            ->map(static fn($method) => $method->toUpper()->toString())
            ->flatMap(Method::maybe(...));
        $url = $parts
            ->get('url')
            ->map(static fn($url) => $url->toString())
            ->flatMap(Url::maybe(...));
        $protocol = $parts
            ->get('protocol')
            ->map(static fn($protocol) => $protocol->toString())
            ->map(static fn($protocol) => match ($protocol) {
                '1.0' => ProtocolVersion::v10,
                '1.1' => ProtocolVersion::v11,
                default => null,
            })
            ->keep(Instance::of(ProtocolVersion::class));

        return Maybe::all($method, $url, $protocol)->map(
            static fn(Method $method, Url $url, ProtocolVersion $protocol) => [
                $method,
                $url,
                $protocol,
            ],
        );
    }
}

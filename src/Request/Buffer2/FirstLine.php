<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer2;

use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
    Factory\Header\TryFactory,
    Factory\Header\Factories,
};
use Innmind\Stream\Capabilities;
use Innmind\TimeContinuum\Clock;
use Innmind\Url\Url;
use Innmind\Immutable\{
    Fold,
    Str,
    Maybe,
    Predicate\Instance,
};

final class FirstLine implements State
{
    private Capabilities $capabilities;
    private TryFactory $factory;
    private Str $buffer;

    private function __construct(
        Capabilities $capabilities,
        TryFactory $factory,
        Str $buffer,
    ) {
        $this->capabilities = $capabilities;
        $this->factory = $factory;
        $this->buffer = $buffer;
    }

    public function __invoke(Str $chunk): Fold
    {
        $buffer = $this->buffer->append($chunk->toString());

        /** @var Fold<null, Request, State> */
        return match ($buffer->contains("\n")) {
            false => Fold::with(new self(
                $this->capabilities,
                $this->factory,
                $buffer,
            )),
            true => $this->parse($buffer),
        };
    }

    public static function new(
        Capabilities $capabilities,
        Clock $clock,
    ): self {
        return new self(
            $capabilities,
            new TryFactory(Factories::default($clock)),
            Str::of('', 'ASCII'),
        );
    }

    /**
     * @return Fold<null, Request, State>
     */
    private function parse(Str $buffer): Fold
    {
        return $buffer
            ->split("\n")
            ->match(
                fn($firstLine, $rest) => $this
                    ->parseHeader($firstLine->rightTrim("\n"))
                    ->map(fn($request) => Headers::new(
                        $this->capabilities,
                        $this->factory,
                        $request,
                    ))
                    ->map(static function($state) use ($rest) {
                        $buffer = Str::of("\n")->join($rest->map(
                            static fn($line) => $line->toString(),
                        ));

                        // do not call the headers state with an empty string
                        // otherwise it will believe it is the empty line
                        // indicating the end of headers
                        // this case happen when the buffer passed to ->parse()
                        // ends with the new line character
                        /** @var Fold<null, Request, State> */
                        return match ($buffer->empty()) {
                            true => Fold::with($state),
                            false => $state($buffer),
                        };
                    })
                    ->match(
                        static fn($fold) => $fold,
                        static fn() => Fold::fail(null),
                    ),
                static fn() => Fold::fail(null),
            );
    }

    /**
     * @return Maybe<Request\Request>
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
            static fn(Method $method, Url $url, ProtocolVersion $protocol) => new Request\Request(
                $url,
                $method,
                $protocol,
            ),
        );
    }
}

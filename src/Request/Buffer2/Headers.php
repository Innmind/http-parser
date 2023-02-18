<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer2;

use Innmind\Http\{
    Message\Request,
    Header,
    Factory\Header\TryFactory,
};
use Innmind\Immutable\{
    Fold,
    Str,
    Maybe,
};

final class Headers implements State
{
    private TryFactory $factory;
    private Request $request;
    private Str $buffer;

    private function __construct(
        TryFactory $factory,
        Request $request,
        Str $buffer,
    ) {
        $this->factory = $factory;
        $this->request = $request;
        $this->buffer = $buffer;
    }

    public function __invoke(Str $chunk): Fold
    {
        $buffer = $this->buffer->append($chunk->toString());

        if (
            $buffer->empty() ||
            $buffer->equals(Str::of("\r"))
        ) {
            return Fold::result($this->request);
        }

        if ($buffer->contains("\n")) {
            return $this->parse($buffer);
        }

        return Fold::with(new self(
            $this->factory,
            $this->request,
            $buffer,
        ));
    }

    public static function new(
        TryFactory $factory,
        Request $request,
    ): self {
        return new self($factory, $request, Str::of('', 'ASCII'));
    }

    /**
     * @return Fold<null, Request, State>
     */
    private function parse(Str $buffer): Fold
    {
        return $buffer
            ->split("\n")
            ->match(
                fn($line, $rest) => $this
                    ->parseHeader($line->rightTrim("\n"))
                    ->map(fn($header) => $this->augment($header))
                    ->map(fn($request) => self::new(
                        $this->factory,
                        $request,
                    ))
                    ->map(static fn($state) => $state(
                        Str::of("\n")->join($rest->map(
                            static fn($line) => $line->toString(),
                        )),
                    ))
                    ->match(
                        static fn($fold) => $fold,
                        static fn() => Fold::fail(null),
                    ),
                static fn() => Fold::fail(null),
            );
    }

    /**
     * @return Maybe<Header>
     */
    private function parseHeader(Str $header): Maybe
    {
        $captured = $header->capture('~^(?<name>[a-zA-Z0-9\-\_\.]+): (?<value>.*)$~');

        return Maybe::all($captured->get('name'), $captured->get('value'))->map(
            fn(Str $name, Str $value) => ($this->factory)($name, $value),
        );
    }

    private function augment(Header $header): Request
    {
        return new Request\Request(
            $this->request->url(),
            $this->request->method(),
            $this->request->protocolVersion(),
            $this->request->headers()($header),
        );
    }
}

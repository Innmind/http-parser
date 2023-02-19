<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Stream\Capabilities;
use Innmind\Http\{
    Message\Request,
    Message\Method,
    Header,
    Header\ContentLength,
    Factory\Header\TryFactory,
};
use Innmind\Immutable\{
    Fold,
    Str,
    Maybe,
    Sequence,
};

final class Headers implements State
{
    private Capabilities $capabilities;
    private TryFactory $factory;
    private Request $request;
    private Str $buffer;

    private function __construct(
        Capabilities $capabilities,
        TryFactory $factory,
        Request $request,
        Str $buffer,
    ) {
        $this->capabilities = $capabilities;
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
            /** @var Fold<null, Request, State> */
            return match ($this->expectBody($buffer)) {
                true => Fold::with(new self( // wait for new line before switching to parsing the body
                    $this->capabilities,
                    $this->factory,
                    $this->request,
                    $buffer,
                )),
                false => Fold::result($this->request),
            };
        }

        if ($buffer->contains("\n")) {
            return $this->parse($buffer);
        }

        /** @var Fold<null, Request, State> */
        return Fold::with(new self(
            $this->capabilities,
            $this->factory,
            $this->request,
            $buffer,
        ));
    }

    public static function new(
        Capabilities $capabilities,
        TryFactory $factory,
        Request $request,
    ): self {
        return new self(
            $capabilities,
            $factory,
            $request,
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
                fn($line, $rest) => match ($line->rightTrim("\r")->empty()) {
                    true => $this->parseBody($rest),
                    false => $this->parseLine($line, $rest),
                },
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

    /**
     * @param Sequence<Str> $rest
     *
     * @return Fold<null, Request, State>
     */
    private function parseLine(Str $line, Sequence $rest): Fold
    {
        return $this
            ->parseHeader($line->rightTrim("\n"))
            ->map(fn($header) => $this->augment($header))
            ->map(fn($request) => self::new(
                $this->capabilities,
                $this->factory,
                $request,
            ))
            ->map(static function($state) use ($rest) {
                $buffer = Str::of("\n")->join($rest->map(
                    static fn($line) => $line->toString(),
                ));

                // do not call the state with an empty string otherwise
                // it will believe it is the empty line indicating the
                // end of headers
                // // this case happen when the buffer passed to ->parse()
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
            );
    }

    /**
     * @param Sequence<Str> $rest
     *
     * @return Fold<null, Request, State>
     */
    private function parseBody(Sequence $rest): Fold
    {
        $buffer = Str::of("\n")->join($rest->map(
            static fn($line) => $line->toString(),
        ));

        if (!$this->expectBody($buffer)) {
            return Fold::result($this->request);
        }

        $body = Body::new($this->capabilities, $this->request);

        /** @var Fold<null, Request, State> */
        return match ($buffer->empty()) {
            true => Fold::with($body),
            false => $body($buffer),
        };
    }

    private function expectBody(Str $buffer): bool
    {
        if (!$buffer->empty()) {
            return true;
        }

        // Transfer-Encoding parsing is not supported yet, this means that a
        // message with a body but without a Content-Length may not be parsed
        $content = $this
            ->request
            ->headers()
            ->find(ContentLength::class)
            ->match(
                static fn($header) => $header,
                static fn() => null,
            );

        if ($content) {
            return $content->length() > 0;
        }

        return !\in_array(
            $this->request->method(),
            [
                Method::get,
                Method::head,
                Method::options,
                Method::trace,
                Method::connect,
                Method::delete,
            ],
            true,
        );
    }
}

<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Stream\{
    Capabilities,
    Bidirectional,
};
use Innmind\Http\{
    Message\Request,
    Header\ContentLength,
};
use Innmind\Filesystem\File\Content;
use Innmind\Immutable\{
    Fold,
    Str,
    Maybe,
    Predicate\Instance,
};

final class Body implements State
{
    private Request $request;
    private Bidirectional $body;
    /** @var Maybe<0|positive-int> */
    private Maybe $expectedLength;
    /** @var 0|positive-int */
    private int $accumulated;
    private Str $buffer;

    /**
     * @param Maybe<0|positive-int> $expectedLength
     * @param 0|positive-int $accumulated
     */
    private function __construct(
        Request $request,
        Bidirectional $body,
        Maybe $expectedLength,
        int $accumulated,
        Str $buffer,
    ) {
        $this->request = $request;
        $this->body = $body;
        $this->expectedLength = $expectedLength;
        $this->accumulated = $accumulated;
        $this->buffer = $buffer;
    }

    public function __invoke(Str $chunk): Fold
    {
        $chunk = $this->buffer->append($chunk->toString());
        $length = $this->expectedLength->match(
            static fn($length) => $length,
            static fn() => null,
        );

        if (\is_int($length)) {
            return $this->accumulateUpTo($chunk, $length);
        }

        if ($chunk->contains("\n") || $chunk->contains("\r")) {
            return $this->buffer($chunk);
        }

        return $this->accumulate($chunk);
    }

    public static function new(
        Capabilities $capabilities,
        Request $request,
    ): self {
        return new self(
            $request,
            $capabilities->temporary()->new(),
            $request
                ->headers()
                ->find(ContentLength::class)
                ->map(static fn($header) => $header->length()),
            0,
            Str::of('', Str\Encoding::ascii),
        );
    }

    /**
     * @param 0|positive-int $length
     *
     * @return Fold<null, Request, State>
     */
    private function accumulateUpTo(Str $chunk, int $length): Fold
    {
        $toWrite = $chunk->take(\max(0, $length - $this->accumulated));

        return $this
            ->body($toWrite)
            ->map(fn($body) => new self(
                $this->request,
                $body,
                $this->expectedLength,
                $this->accumulated + $toWrite->length(),
                Str::of('', Str\Encoding::ascii),
            ))
            ->flatMap(static fn($self) => $self->checkLength($length));
    }

    /**
     * @return Fold<null, Request, State>
     */
    private function buffer(Str $chunk): Fold
    {
        if ($chunk->endsWith("\n") || $chunk->endsWith("\r")) {
            if ($chunk->endsWith("\n\n")) {
                return $this->endWith($chunk->dropEnd(2));
            }

            if ($chunk->endsWith("\r\n\r\n")) {
                return $this->endWith($chunk->dropEnd(4));
            }

            // wait for extra chunks to see if we're reaching the end of the body
            /** @var Fold<null, Request, State> */
            return Fold::with(new self(
                $this->request,
                $this->body,
                $this->expectedLength,
                $this->accumulated,
                $chunk,
            ));
        }

        return $this->accumulate($chunk);
    }

    /**
     * @return Fold<null, Request, State>
     */
    private function endWith(Str $chunk): Fold
    {
        /** @var Fold<null, Request, State> */
        return $this
            ->body($chunk)
            ->map(fn($body) => new Request\Request(
                $this->request->url(),
                $this->request->method(),
                $this->request->protocolVersion(),
                $this->request->headers(),
                Content\OfStream::of($body),
            ))
            ->flatMap(static fn($request) => Fold::result($request));
    }

    /**
     * @param 0|positive-int $length
     *
     * @return Fold<null, Request, State>
     */
    private function checkLength(int $length): Fold
    {
        if ($this->accumulated === $length) {
            /** @var Fold<null, Request, State> */
            return Fold::result(new Request\Request(
                $this->request->url(),
                $this->request->method(),
                $this->request->protocolVersion(),
                $this->request->headers(),
                Content\OfStream::of($this->body),
            ));
        }

        /** @var Fold<null, Request, State> */
        return Fold::with($this);
    }

    /**
     * @return Fold<null, Request, State>
     */
    private function accumulate(Str $chunk): Fold
    {
        /** @var Fold<null, Request, State> */
        return $this
            ->body($chunk)
            ->map(fn($body) => new self(
                $this->request,
                $body,
                $this->expectedLength,
                $this->accumulated + $chunk->length(),
                Str::of('', Str\Encoding::ascii),
            ));
    }

    /**
     * @return Fold<null, Request, Bidirectional>
     */
    private function body(Str $chunk): Fold
    {
        return $this
            ->body
            ->write($chunk)
            ->maybe()
            ->keep(Instance::of(Bidirectional::class))
            ->match(
                static fn($body) => Fold::with($body),
                static fn() => Fold::fail(null), // failed to write to body or not bidirectional
            );
    }
}

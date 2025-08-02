<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame;

use Innmind\Http\{
    Headers,
    Header\ContentLength,
};
use Innmind\Filesystem\File\Content;
use Innmind\IO\Frame;
use Innmind\Immutable\{
    Str,
    Maybe,
    Sequence,
    Pair,
};

final class Body
{
    /**
     * @param Maybe<Headers> $headers
     *
     * @return Frame<Content>
     */
    public static function of(Maybe $headers): Frame
    {
        /** @psalm-suppress InvalidArgument Because of the filter */
        return $headers
            ->flatMap(
                static fn($headers) => $headers
                    ->find(ContentLength::class)
                    ->map(static fn($header) => $header->length())
                    ->filter(static fn($length) => $length > 0),
            )
            ->match(
                self::bounded(...),
                self::unbounded(...),
            )
            ->map(Content::ofChunks(...));
    }

    /**
     * @param positive-int $length
     *
     * @return Frame<Sequence<Str>>
     */
    private static function bounded(int $length): Frame
    {
        /**
         * @psalm-suppress MixedOperand
         * @var Frame<Sequence<Str>>
         */
        return match (\min($length, 8192)) {
            $length => Frame::chunk($length)->strict()->map(Sequence::of(...)),
            default => Frame::sequence(
                Frame::chunk(8192)->loose(),
            )
                ->map(
                    static fn($lines) => $lines
                        ->map(static fn($line) => $line->unwrap()) // todo find a way to not throw
                        ->map(static fn($line) => new Pair(
                            $line->length(),
                            $line,
                        ))
                        ->aggregate(static fn(Pair $a, Pair $b) => Sequence::of(
                            $a,
                            new Pair(
                                $a->key() + $b->key(),
                                $b->value(),
                            ),
                        ))
                        ->takeWhile(static fn($pair) => $pair->key() < $length)
                        ->map(static fn($pair) => $pair->value()),
                ),
        };
    }

    /**
     * @return Frame<Sequence<Str>>
     */
    private static function unbounded(): Frame
    {
        return Body\Unbounded::new();
    }
}

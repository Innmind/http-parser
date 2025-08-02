<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame\Body;

use Innmind\IO\Frame;
use Innmind\Immutable\{
    Sequence,
    Str,
    Monoid\Concat,
};

/**
 * @internal
 */
final class Unbounded
{
    /**
     * @return Frame<Sequence<Str>>
     */
    public static function new(): Frame
    {
        return Frame::sequence(
            Frame::chunk(8192)->loose(),
        )
            ->map(
                static fn($chunks) => $chunks
                    ->map(static fn($chunk) => $chunk->unwrap()) // todo find a way to not throw
                    ->flatMap(static fn($chunk) => match ($chunk->empty()) {
                        true => Sequence::of($chunk),
                        false => $chunk->chunk(),
                    })
                    ->windows(4)
                    ->via(self::bound(...))
                    ->aggregate(static fn(Str $a, Str $b) => match (true) {
                        $a->length() < 8192 => Sequence::of($a->append($b)),
                        default => Sequence::of($a, $b),
                    }),
            );
    }

    /**
     * @param Sequence<Sequence<Str>> $chunks
     *
     * @return Sequence<Str>
     */
    private static function bound(Sequence $chunks): Sequence
    {
        $end = Str::of('');

        return $chunks
            ->flatMap(static fn($chunk) => match (true) {
                $chunk
                    ->fold(new Concat)
                    ->empty() => Sequence::of($end),
                $chunk
                    ->fold(new Concat)
                    ->equals(Str::of("\r\n\r\n")) => Sequence::of($end),
                $chunk
                    ->drop(2)
                    ->fold(new Concat)
                    ->equals(Str::of("\n\n")) => $chunk->take(2)->add($end),
                default => $chunk->take(1),
            })
            ->takeWhile(static fn($chunk) => $chunk !== $end);
    }
}

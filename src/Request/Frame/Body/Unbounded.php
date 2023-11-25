<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame\Body;

use Innmind\IO\{
    Readable\Frame,
    Readable\Frame\Map,
    Readable\Frame\Filter,
    Readable\Frame\FlatMap,
    Exception\FailedToLoadStream,
};
use Innmind\Immutable\{
    Sequence,
    Maybe,
    Str,
};

/**
 * @internal
 * @implements Frame<Sequence<Str>>
 */
final class Unbounded implements Frame
{
    private function __construct()
    {
    }

    public function __invoke(
        callable $read,
        callable $readLine,
    ): Maybe {
        return Maybe::just(Sequence::lazy(function() use ($read) {
            $buffer = Str::of('');

            do {
                $chunk = $read(8192)->match(
                    static fn($chunk) => $chunk,
                    static fn() => throw new FailedToLoadStream,
                );

                // use prepend to make sure to use the encoding of the chunk and
                // not the default utf8 from the initial buffer
                $tmpBuffer = $chunk
                    ->prepend($buffer)
                    ->takeEnd(4);
                $endReached = $this->end($tmpBuffer);

                if ($endReached) {
                    $chunk = match ($chunk->endsWith("\n\n")) {
                        true => $chunk->dropEnd(2),
                        false => $chunk->dropEnd(4),
                    };
                }

                yield $chunk;

                $buffer = $tmpBuffer;
            } while (!$endReached);
        }));
    }

    public static function new(): self
    {
        return new self;
    }

    public function filter(callable $predicate): Frame
    {
        return Filter::of($this, $predicate);
    }

    public function map(callable $map): Frame
    {
        return Map::of($this, $map);
    }

    public function flatMap(callable $map): Frame
    {
        return FlatMap::of($this, $map);
    }

    private function end(Str $buffer): bool
    {
        return $buffer->empty() || $buffer->equals(Str::of("\r\n\r\n")) || $buffer->takeEnd(2)->equals(Str::of("\n\n"));
    }
}

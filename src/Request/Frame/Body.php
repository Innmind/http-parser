<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame;

use Innmind\Http\{
    Headers,
    Header\ContentLength,
};
use Innmind\Filesystem\File\Content;
use Innmind\IO\Readable\Frame;
use Innmind\Immutable\{
    Str,
    Maybe,
    Sequence,
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
        $accumulated = 0;

        return match (\min($length, 8192)) {
            $length => Frame\Chunk::of($length)->map(Sequence::of(...)),
            default => Frame\Chunks::of(8192)->until(
                1,
                static function($chunk) use (&$accumulated, $length) {
                    /**
                     * @psalm-suppress MixedOperand
                     * @psalm-suppress MixedAssignment
                     */
                    $accumulated += $chunk->length();

                    return $accumulated >= $length;
                },
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

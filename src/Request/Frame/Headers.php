<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame;

use Innmind\Http\{
    Headers as Model,
    Header,
    Factory\Header\TryFactory,
};
use Innmind\IO\Readable\Frame;
use Innmind\Immutable\{
    Str,
    Maybe,
    Sequence,
};

final class Headers
{
    /**
     * @return Frame<Maybe<Model>>
     */
    public static function of(TryFactory $factory): Frame
    {
        return Frame\Sequence::of(
            Frame\Line::new()->map(static fn($line) => $line->trim()),
        )
            ->until(static fn($line) => $line->empty())
            ->map(
                static fn($lines) => $lines
                    ->filter(static fn($line) => !$line->empty())
                    ->map(static fn($line) => self::parse($line, $factory)),
            )
            ->map(self::build(...));
    }

    /**
     * @return Maybe<Header>
     */
    private static function parse(Str $line, TryFactory $factory): Maybe
    {
        $captured = $line->capture('~^(?<name>[a-zA-Z0-9\-\_\.]+): (?<value>.*)$~');

        return Maybe::all($captured->get('name'), $captured->get('value'))->map(
            static fn(Str $name, Str $value) => $factory($name, $value),
        );
    }

    /**
     * @param Sequence<Maybe<Header>> $headers
     *
     * @return Maybe<Model>
     */
    private static function build(Sequence $headers): Maybe
    {
        return $headers->match(
            static fn($header, $rest) => Maybe::all($header, ...$rest->toList())->map(Model::of(...)),
            static fn() => Maybe::just(Model::of()),
        );
    }
}

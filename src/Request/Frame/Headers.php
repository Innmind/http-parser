<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame;

use Innmind\Http\{
    Headers as Model,
    Header,
    Factory\Header\Factory,
};
use Innmind\IO\Frame;
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
    public static function of(Factory $factory): Frame
    {
        return Frame::sequence(
            Frame::line()->map(static fn($line) => $line->trim()),
        )
            ->map(
                static fn($lines) => $lines
                    ->map(static fn($line) => $line->unwrap()) // todo find a way to not throw
                    ->takeWhile(static fn($line) => !$line->empty()),
            )
            ->map(
                static fn($lines) => $lines
                    ->filter(static fn($line) => !$line->empty())
                    ->map(static fn($line) => self::parse($line, $factory)),
            )
            ->map(self::build(...));
    }

    /**
     * @return Maybe<Header|Header\Custom>
     */
    private static function parse(Str $line, Factory $factory): Maybe
    {
        $captured = $line->capture('~^(?<name>[a-zA-Z0-9\-\_\.]+): (?<value>.*)$~');

        return Maybe::all($captured->get('name'), $captured->get('value'))->map(
            static fn(Str $name, Str $value) => $factory($name, $value),
        );
    }

    /**
     * @param Sequence<Maybe<Header|Header\Custom>> $headers
     *
     * @return Maybe<Model>
     */
    private static function build(Sequence $headers): Maybe
    {
        return $headers
            ->sink(Model::of())
            ->maybe(static fn($headers, $header) => $header->map(
                $headers,
            ));
    }
}

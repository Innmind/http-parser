<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\TimeContinuum\Clock;
use Innmind\Http\Message\Request;
use Innmind\Immutable\{
    Sequence,
    Str,
    Maybe,
};

final class Parse
{
    private Clock $clock;

    public function __construct(Clock $clock)
    {
        $this->clock = $clock;
    }

    /**
     * @param Sequence<Str> $chunks
     *
     * @return Maybe<Request>
     */
    public function __invoke(Sequence $chunks): Maybe
    {
        return $chunks
            ->reduce(
                Buffer::new($this->clock),
                static fn(Buffer $buffer, $chunk) => $buffer->add($chunk),
            )
            ->finish();
    }
}

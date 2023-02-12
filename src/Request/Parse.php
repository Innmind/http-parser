<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\TimeContinuum\Clock;
use Innmind\Http\Message\Request;
use Innmind\Stream\Capabilities;
use Innmind\Immutable\{
    Sequence,
    Str,
    Maybe,
};

final class Parse
{
    private Clock $clock;
    private Capabilities $capabilities;

    public function __construct(Clock $clock, Capabilities $capabilities)
    {
        $this->clock = $clock;
        $this->capabilities = $capabilities;
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
                Buffer::new($this->clock, $this->capabilities),
                static fn(Buffer $buffer, $chunk) => $buffer->add($chunk),
            )
            ->finish();
    }
}

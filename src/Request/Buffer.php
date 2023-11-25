<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Buffer\{
    State,
    FirstLine,
};
use Innmind\Stream\Capabilities;
use Innmind\TimeContinuum\Clock;
use Innmind\Http\Request;
use Innmind\Immutable\{
    Fold,
    Str,
};

final class Buffer
{
    private State $state;

    private function __construct(State $state)
    {
        $this->state = $state;
    }

    /**
     * @return Fold<null, Request, self>
     */
    public function __invoke(Str $chunk): Fold
    {
        return ($this->state)($chunk)->map(
            static fn($state) => new self($state),
        );
    }

    public static function new(
        Capabilities $capabilities,
        Clock $clock,
    ): self {
        return new self(FirstLine::new($capabilities, $clock));
    }
}

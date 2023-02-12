<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Buffer\{
    State,
    Uninitialized,
};
use Innmind\TimeContinuum\Clock;
use Innmind\Http\Message\Request;
use Innmind\Immutable\{
    Maybe,
    Str,
};

final class Buffer
{
    private State $state;

    private function __construct(State $state)
    {
        $this->state = $state;
    }

    public static function new(Clock $clock): self
    {
        return new self(Uninitialized::new($clock));
    }

    public function add(Str $chunk): self
    {
        return new self($this->state->add($chunk));
    }

    /**
     * @return Maybe<Request>
     */
    public function finish(): Maybe
    {
        return $this->state->finish();
    }
}

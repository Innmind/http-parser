<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\Message\Request;
use Innmind\Immutable\{
    Maybe,
    Str,
};

final class Failure implements State
{
    public function add(Str $chunk): self
    {
        return new $this;
    }

    public function finish(): Maybe
    {
        /** @var Maybe<Request> */
        return Maybe::nothing();
    }
}

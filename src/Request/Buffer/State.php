<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\Message\Request;
use Innmind\Immutable\{
    Maybe,
    Str,
};

interface State
{
    public function add(Str $chunk): self;

    /**
     * @return Maybe<Request>
     */
    public function finish(): Maybe;
}

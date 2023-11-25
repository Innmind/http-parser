<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Buffer;

use Innmind\Http\Request;
use Innmind\Immutable\{
    Fold,
    Str,
};

interface State
{
    /**
     * @return Fold<null, Request, self>
     */
    public function __invoke(Str $chunk): Fold;
}

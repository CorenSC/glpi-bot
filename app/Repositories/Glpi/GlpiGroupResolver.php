<?php

declare(strict_types=1);

namespace App\Repositories\Glpi;

final class GlpiGroupResolver
{
    public function __construct(private readonly GlpiTicketApiRepository $tickets)
    {
    }
}

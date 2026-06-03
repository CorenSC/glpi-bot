<?php

declare(strict_types=1);

namespace App\Repositories\Glpi;

final class GlpiCategoryResolver
{
    public function __construct(private readonly GlpiTicketApiRepository $tickets)
    {
    }
}

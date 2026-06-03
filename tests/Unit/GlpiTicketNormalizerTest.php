<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GlpiAi\GlpiTicketNormalizer;
use Tests\TestCase;

class GlpiTicketNormalizerTest extends TestCase
{
    public function test_it_removes_html_scripts_and_normalizes_spaces(): void
    {
        $normalizer = new GlpiTicketNormalizer();

        $clean = $normalizer->clean('<script>alert(1)</script><p>Erro&nbsp;500 no sistema</p>   <style>.x{}</style>');

        $this->assertSame('Erro 500 no sistema', $clean);
    }

    public function test_it_generates_stable_canonical_hash(): void
    {
        $normalizer = new GlpiTicketNormalizer();
        $ticket = ['title' => 'Falha VPN', 'category_path' => 'Rede > VPN', 'content' => 'Erro 809'];

        $first = $normalizer->hash($normalizer->canonicalize($ticket));
        $second = $normalizer->hash($normalizer->canonicalize($ticket));

        $this->assertSame($first, $second);
    }
}

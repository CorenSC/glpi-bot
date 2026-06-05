<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

final class GlpiTicketNormalizer
{
    public function clean(?string $text): string
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $text) ?? $text;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/(?:^|\R)>.*(?:\R|$)/u', "\n", $text) ?? $text;
        $text = preg_replace('/(--\s*$|Atenciosamente,.*$|Enviado do meu.*$)/isu', ' ', $text) ?? $text;
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function canonicalize(array $ticket): string
    {
        $title = $this->clean($ticket['title'] ?? '');
        $titleCategory = $this->extractTitleCategory($title);
        $category = $this->clean($ticket['category_path'] ?? $ticket['category_name'] ?? $ticket['title_category'] ?? $titleCategory ?? '');

        $lines = [
            'Título: '.$title,
            'Categoria detectada no título: '.$this->clean($titleCategory ?? ''),
            'Categoria informada: '.$category,
            'Descrição: '.$this->clean($ticket['content'] ?? $ticket['original_content'] ?? ''),
            'Solução: '.$this->clean($ticket['solution_text'] ?? ''),
            'Grupo atribuído: '.$this->clean($ticket['assigned_group_name'] ?? ''),
            'Técnico atribuído: '.$this->clean($ticket['assigned_technician_name'] ?? ''),
            'Técnico solucionador: '.$this->clean($ticket['solver_technician_name'] ?? ''),
            'Histórico resumido: '.$this->clean($ticket['followup_summary'] ?? ''),
        ];

        return $this->limit(implode("\n", $lines), (int) config('glpi-ai.text_limit', 12000));
    }

    public function hash(string $canonicalText): string
    {
        return hash('sha256', preg_replace('/\s+/u', ' ', trim($canonicalText)) ?? trim($canonicalText));
    }

    public function limit(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit));
    }

    public function extractTitleCategory(?string $title): ?string
    {
        $title = trim((string) $title);
        if (preg_match('/^\[([^\]]+)\]/u', $title, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}

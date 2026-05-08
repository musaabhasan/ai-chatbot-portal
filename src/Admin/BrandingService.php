<?php

declare(strict_types=1);

namespace ChatbotPortal\Admin;

use PDO;

final class BrandingService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function cssForBot(string $slug): string
    {
        $statement = $this->db->prepare(
            "SELECT bp.*
             FROM branding_profiles bp
             INNER JOIN bot_instances b ON b.id = bp.bot_id
             WHERE b.slug = :slug
             LIMIT 1"
        );
        $statement->execute(['slug' => $slug]);
        $profile = $statement->fetch() ?: [];

        $primary = $profile['primary_color'] ?? '#0f766e';
        $accent = $profile['accent_color'] ?? '#2563eb';
        $background = $profile['background_color'] ?? '#f8fafc';
        $text = $profile['text_color'] ?? '#111827';
        $font = $profile['font_family'] ?? 'Inter, system-ui, sans-serif';

        return <<<CSS
:root {
  --brand-primary: {$primary};
  --brand-accent: {$accent};
  --brand-background: {$background};
  --brand-text: {$text};
  --brand-font: {$font};
}
CSS;
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Services;

final class DocumentTemplateRenderer
{
    /** @param array<string, string> $flatContext */
    public function render(string $template, array $flatContext): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($flatContext): string {
                $key = $matches[1];

                return array_key_exists($key, $flatContext) ? e($flatContext[$key]) : '';
            },
            $template,
        );
    }
}

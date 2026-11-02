<?php

declare(strict_types=1);

namespace App\Application\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class DocumentTemplatePdfService
{
    public function stream(string $title, string $htmlBody, string $filename): Response
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'document';

        $pdf = Pdf::loadView('document-templates.layout', [
            'title' => $title,
            'content' => $htmlBody,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 110);

        return $pdf->download($safeName.'.pdf');
    }

    public function store(string $title, string $htmlBody, string $relativePath): string
    {
        $pdf = Pdf::loadView('document-templates.layout', [
            'title' => $title,
            'content' => $htmlBody,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 110);

        $binary = $pdf->output();
        $fullPath = storage_path('app/public/'.$relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $binary);

        return $relativePath;
    }
}

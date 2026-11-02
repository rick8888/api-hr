<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        @@page { margin: 18mm 16mm 20mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #111;
            line-height: 1.45;
        }
        h1 { font-size: 14pt; margin: 0 0 12px; }
        h2 { font-size: 11pt; margin: 16px 0 8px; }
        p { margin: 0 0 8px; }
        .meta { font-size: 9pt; color: #444; margin-bottom: 16px; }
    </style>
</head>
<body>
{!! $content !!}
</body>
</html>

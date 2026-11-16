<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color: #4b5563; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; }
        th { background: #f3f4f6; }
        .number { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .totals { margin-top: 16px; width: auto; }
        .notice { margin-top: 16px; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Rapport des déclarations fiscales et sociales</h1>
    <div class="meta">
        {{ $report['company']['name'] }}@if($report['company']['country']) — {{ $report['company']['country'] }}@endif
        — Période : {{ $report['period'] }} — Bulletins pris en compte : {{ $report['payroll_count'] }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Organisme</th>
                <th>Obligation</th>
                <th>Paiement</th>
                <th>Codes de paie liés</th>
                <th class="number">Part salariale</th>
                <th class="number">Part patronale</th>
                <th class="number">Total à reverser</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['obligations'] as $obligation)
                @if(! $obligation['is_calculable'] || count($obligation['amounts']) === 0)
                    <tr>
                        <td>{{ $obligation['authority'] ?: '—' }}</td>
                        <td>{{ $obligation['name'] }}</td>
                        <td>{{ $obligation['payment']['is_paid'] ? 'Payée le '.$obligation['payment']['paid_at'] : 'Non payée' }}</td>
                        <td class="muted">{{ $obligation['is_calculable'] ? implode(', ', $obligation['linked_rule_codes']) : 'À configurer' }}</td>
                        <td class="number">—</td>
                        <td class="number">—</td>
                        <td class="number">—</td>
                    </tr>
                @else
                    @foreach($obligation['amounts'] as $amount)
                        <tr>
                            <td>{{ $obligation['authority'] ?: '—' }}</td>
                            <td>{{ $obligation['name'] }}</td>
                            <td>{{ $obligation['payment']['is_paid'] ? 'Payée le '.$obligation['payment']['paid_at'] : 'Non payée' }}</td>
                            <td class="muted">
                                {{ $obligation['is_calculable'] ? implode(', ', $obligation['linked_rule_codes']) : 'À configurer' }}
                                @if(! empty($amount['missing_employer_codes']))
                                    <br>Part patronale absente : {{ implode(', ', $amount['missing_employer_codes']) }}
                                @endif
                            </td>
                            <td class="number">{{ number_format($amount['employee'], 2, ',', ' ') }} {{ $amount['currency'] }}</td>
                            <td class="number">{{ number_format($amount['employer'], 2, ',', ' ') }} {{ $amount['currency'] }}</td>
                            <td class="number">
                                @if($amount['is_complete'])
                                    {{ number_format($amount['total'], 2, ',', ' ') }} {{ $amount['currency'] }}
                                @else
                                    Partiel : {{ number_format($amount['known_total'], 2, ',', ' ') }} {{ $amount['currency'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <thead>
            <tr>
                <th>Devise</th>
                <th class="number">Total salarial</th>
                <th class="number">Total patronal</th>
                <th class="number">Total à reverser</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['totals_by_currency'] as $total)
                <tr>
                    <td>{{ $total['currency'] }}</td>
                    <td class="number">{{ number_format($total['employee'], 2, ',', ' ') }}</td>
                    <td class="number">{{ number_format($total['employer'], 2, ',', ' ') }}</td>
                    <td class="number">
                        @if($total['is_complete'])
                            {{ number_format($total['total'], 2, ',', ' ') }}
                        @else
                            Partiel : {{ number_format($total['known_total'], 2, ',', ' ') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="notice">{{ $report['notice'] }} Les obligations sans code de paie associé et les cotisations patronales manquantes ne sont pas présentées comme des totaux complets.</p>
</body>
</html>

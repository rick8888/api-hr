<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payslip['code'] ?? 'Payslip' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .meta td { border: none; padding: 3px 0; }
        .right { text-align: right; }
        .net { font-size: 16px; font-weight: bold; margin-top: 16px; }
    </style>
</head>
<body>
    <h1>Payslip {{ $payslip['code'] ?? '' }}</h1>
    <table class="meta">
        <tr>
            <td><strong>Period:</strong> {{ $payslip['period_label'] ?? '—' }}</td>
            <td><strong>Status:</strong> {{ $payslip['status'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Employee:</strong> {{ $payslip['employee']['name'] ?? '—' }}</td>
            <td><strong>Department:</strong> {{ $payslip['employee']['department'] ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Designation:</strong> {{ $payslip['employee']['designation'] ?? '—' }}</td>
            <td><strong>Currency:</strong> {{ $payslip['currency'] ?? 'USD' }}</td>
        </tr>
    </table>

    <h2>Earnings</h2>
    <table>
        <thead>
            <tr>
                <th>Label</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($payslip['earnings'] ?? []) as $line)
                <tr>
                    <td>{{ $line['label'] }}</td>
                    <td class="right">{{ number_format((float) $line['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Basic Salary</td>
                    <td class="right">{{ number_format((float) ($payslip['basic_salary'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Allowances &amp; Benefits</td>
                    <td class="right">{{ number_format((float) ($payslip['allowances'] ?? 0), 2) }}</td>
                </tr>
            @endforelse
            <tr>
                <td><strong>Total Earnings</strong></td>
                <td class="right"><strong>{{ number_format((float) ($payslip['total_earnings'] ?? 0), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <h2>Deductions</h2>
    <table>
        <thead>
            <tr>
                <th>Label</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($payslip['deduction_details'] ?? []) as $line)
                <tr>
                    <td>{{ $line['label'] }}</td>
                    <td class="right">{{ number_format((float) $line['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No detail lines</td>
                </tr>
            @endforelse
            <tr>
                <td><strong>Total Deductions</strong></td>
                <td class="right"><strong>{{ number_format((float) ($payslip['deductions'] ?? 0), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p class="net">Net Salary: {{ number_format((float) ($payslip['net_salary'] ?? 0), 2) }} {{ $payslip['currency'] ?? '' }}</p>
</body>
</html>

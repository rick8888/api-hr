<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice['invoice_number'] ?? 'Invoice' }}</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.45;
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 22px 24px;
            border-radius: 8px;
            margin-bottom: 22px;
        }
        .header-title {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #cbd5e1;
            margin: 0 0 6px 0;
        }
        .header-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .header-meta {
            margin-top: 10px;
            font-size: 11px;
            color: #e2e8f0;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: bold;
            background: #34d399;
            color: #064e3b;
        }
        .badge-pending { background: #fbbf24; color: #78350f; }
        .badge-overdue { background: #f87171; color: #7f1d1d; }
        .badge-other { background: #94a3b8; color: #0f172a; }
        .cols { width: 100%; margin-bottom: 18px; }
        .cols td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin: 0 0 6px 0;
        }
        .name { font-size: 14px; font-weight: bold; margin: 0 0 4px 0; }
        .muted { color: #64748b; margin: 0 0 2px 0; }
        .meta {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .meta td {
            width: 25%;
            padding: 10px 12px;
            vertical-align: top;
        }
        .meta .k {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
        }
        .meta .v {
            font-size: 12px;
            font-weight: bold;
            margin-top: 4px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.items th {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            padding: 10px 12px;
        }
        table.items th.right,
        table.items td.right { text-align: right; }
        table.items td {
            border-bottom: 1px solid #f1f5f9;
            padding: 12px;
            vertical-align: top;
        }
        .item-desc { font-weight: bold; }
        .item-sub { color: #64748b; font-size: 11px; margin-top: 3px; }
        .totals {
            width: 280px;
            margin-left: auto;
        }
        .totals td {
            padding: 6px 0;
        }
        .totals .k { color: #64748b; }
        .totals .v { text-align: right; font-weight: bold; }
        .totals .balance td {
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 14px;
        }
        .notes {
            margin-top: 22px;
            padding: 12px 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            color: #92400e;
        }
        .footer {
            margin-top: 28px;
            text-align: center;
            color: #94a3b8;
            font-size: 11px;
            border-top: 1px solid #e2e8f0;
            padding-top: 14px;
        }
    </style>
</head>
<body>
@php
    $status = strtolower((string) ($invoice['status'] ?? ''));
    $badgeClass = match (true) {
        $status === 'paid' => 'badge',
        in_array($status, ['pending', 'partial'], true) => 'badge badge-pending',
        in_array($status, ['overdue', 'failed'], true) => 'badge badge-overdue',
        default => 'badge badge-other',
    };

    $formatDate = static function (?string $value): string {
        if (! $value) {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('M j, Y');
        } catch (\Throwable) {
            return $value;
        }
    };

    $issuer = $invoice['issuer'] ?? [];
    $billTo = $invoice['bill_to'] ?? [];
    $lineItems = $invoice['line_items'] ?? [];

    $cityLine = trim(implode(' ', array_filter([
        $billTo['postal_code'] ?? null,
        $billTo['city'] ?? null,
        $billTo['state'] ?? null,
    ])));
@endphp

<div class="header">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <p class="header-title">Invoice</p>
                <p class="header-number">{{ $invoice['invoice_number'] ?? '—' }}</p>
                <p class="header-meta">{{ $issuer['name'] ?? 'OpenCore Business Suite' }}</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="{{ $badgeClass }}">{{ $invoice['status'] ?? '—' }}</span>
                <p class="header-meta" style="margin-top: 10px;">
                    Issued {{ $formatDate($invoice['date'] ?? null) }}
                </p>
                @if (! empty($invoice['due_date']))
                    <p class="header-meta">Due {{ $formatDate($invoice['due_date']) }}</p>
                @endif
            </td>
        </tr>
    </table>
</div>

<table class="cols" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <p class="label">From</p>
            <p class="name">{{ $issuer['name'] ?? 'OpenCore Business Suite' }}</p>
            @if (! empty($issuer['email']))
                <p class="muted">{{ $issuer['email'] }}</p>
            @endif
            @if (! empty($issuer['website']))
                <p class="muted">{{ $issuer['website'] }}</p>
            @endif
        </td>
        <td>
            <p class="label">Bill to</p>
            <p class="name">{{ $billTo['name'] ?? '—' }}</p>
            @if (! empty($billTo['legal_name']) && ($billTo['legal_name'] ?? '') !== ($billTo['name'] ?? ''))
                <p class="muted">{{ $billTo['legal_name'] }}</p>
            @endif
            @if (! empty($billTo['street']))
                <p class="muted">{{ $billTo['street'] }}</p>
            @endif
            @if ($cityLine !== '')
                <p class="muted">{{ $cityLine }}</p>
            @endif
            @if (! empty($billTo['country']))
                <p class="muted">{{ $billTo['country'] }}</p>
            @endif
            @if (! empty($billTo['email']))
                <p class="muted">{{ $billTo['email'] }}</p>
            @endif
            @if (! empty($billTo['phone']))
                <p class="muted">{{ $billTo['phone'] }}</p>
            @endif
            @if (! empty($billTo['tax_id']))
                <p class="muted">Tax ID: {{ $billTo['tax_id'] }}</p>
            @endif
        </td>
    </tr>
</table>

<table class="meta" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <div class="k">Invoice #</div>
            <div class="v">{{ $invoice['invoice_number'] ?? '—' }}</div>
        </td>
        <td>
            <div class="k">Issue date</div>
            <div class="v">{{ $formatDate($invoice['date'] ?? null) }}</div>
        </td>
        <td>
            <div class="k">Period</div>
            <div class="v">{{ $invoice['period'] ?? '—' }}</div>
        </td>
        <td>
            <div class="k">Paid on</div>
            <div class="v">{{ $formatDate($invoice['paid_at'] ?? null) }}</div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="right">Qty</th>
            <th class="right">Unit price</th>
            <th class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineItems as $item)
            <tr>
                <td>
                    <div class="item-desc">{{ $item['description'] ?? '—' }}</div>
                    @if (! empty($invoice['plan_name']))
                        <div class="item-sub">Plan: {{ $invoice['plan_name'] }}</div>
                    @endif
                </td>
                <td class="right">{{ $item['quantity'] ?? 1 }}</td>
                <td class="right">{{ $item['unit_price'] ?? '—' }}</td>
                <td class="right">{{ $item['total'] ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No line items.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals" cellpadding="0" cellspacing="0">
    <tr>
        <td class="k">Subtotal</td>
        <td class="v">{{ $invoice['amount'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="k">Amount paid</td>
        <td class="v">{{ $invoice['amount_paid'] ?? '—' }}</td>
    </tr>
    <tr class="balance">
        <td class="k">Balance due</td>
        <td class="v">{{ $invoice['balance'] ?? ($invoice['amount'] ?? '—') }}</td>
    </tr>
</table>

@if (! empty($invoice['notes']))
    <div class="notes">
        <strong>Notes</strong><br>
        {{ $invoice['notes'] }}
    </div>
@endif

<div class="footer">
    Thank you for your business.<br>
    This invoice was generated automatically for your subscription payment.
</div>
</body>
</html>

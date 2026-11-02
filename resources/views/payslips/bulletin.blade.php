<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fiche de paie</title>
    <style>
        @@page { margin: 9mm 10mm 10mm 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #111;
            margin: 0;
            line-height: 1.35;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

        /* Header */
        .head { margin-bottom: 10px; }
        .head td { width: 50%; }
        .co {
            border: 1px solid #7eb3d9;
            padding: 8px 10px 7px;
        }
        .co-name {
            font-size: 10.5pt;
            font-weight: bold;
            letter-spacing: 0.2px;
            margin: 0 0 3px;
            text-transform: uppercase;
        }
        .co-line { margin: 0; font-size: 7.5pt; color: #222; }
        .co-ids { margin: 3px 0 0; font-size: 7.5pt; }
        .logo { max-width: 70px; max-height: 52px; }
        .title-wrap { padding: 4px 0 0 16px; }
        .doc-title {
            font-size: 20pt;
            font-weight: bold;
            margin: 0 0 10px;
            color: #111;
            letter-spacing: -0.4px;
        }
        .period { margin: 0 0 3px; font-size: 8.5pt; }
        .period strong { font-weight: bold; }

        /* Employee */
        .who { margin: 0 0 10px; }
        .who td { width: 50%; padding: 0; }
        .meta { font-size: 7.6pt; line-height: 1.55; }
        .meta .k { color: #333; }
        .emp-name {
            font-size: 11pt;
            font-weight: bold;
            margin: 0 0 4px;
            text-transform: uppercase;
        }
        .emp-addr { margin: 0; font-size: 8pt; color: #222; }

        /* Main grid — light header, thin blue rules like a real bulletin */
        .grid { width: 100%; border: 1px solid #6aa3cf; }
        .grid th {
            background: #d7ebf8;
            color: #123;
            font-weight: bold;
            font-size: 7pt;
            text-align: center;
            padding: 5px 4px;
            border: 1px solid #6aa3cf;
        }
        .grid th.rub { text-align: left; padding-left: 7px; width: 38%; }
        .grid th.w13 { width: 12.4%; }
        .grid td {
            border-left: 1px solid #8ebddc;
            border-right: 1px solid #8ebddc;
            border-bottom: 1px solid #e6eef4;
            padding: 2.5px 6px;
            font-size: 7.6pt;
        }
        .grid td.rub { border-left: none; padding-left: 7px; }
        .row-group td {
            font-weight: bold;
            font-size: 7.4pt;
            text-transform: uppercase;
            padding-top: 5px;
            padding-bottom: 3px;
            border-bottom: 1px solid #c5dcec;
            background: #fff;
            color: #111;
        }
        .row-bold td { font-weight: bold; }
        .row-brut td {
            font-weight: bold;
            border-top: 1px solid #6aa3cf;
            border-bottom: 1px solid #6aa3cf;
        }
        .row-total td {
            font-weight: bold;
            background: #eef6fb;
            border-top: 1px solid #6aa3cf;
            border-bottom: 1px solid #6aa3cf;
        }

        .net-social td {
            background: #d7ebf8;
            font-weight: bold;
            font-size: 8.2pt;
            padding: 6px 8px;
            border: 1px solid #6aa3cf;
        }

        .net-before {
            margin-top: 7px;
            border: 1.8px solid #4f92c7;
        }
        .net-before td {
            font-weight: bold;
            padding: 8px 10px;
            font-size: 10pt;
        }
        .net-before .amt { font-size: 11pt; width: 30%; }

        /* Bottom summaries — no colored banners */
        .sum { margin-top: 9px; }
        .sum > td { width: 33.33%; padding: 0 5px 0 0; }
        .sum > td:last-child { padding-right: 0; }
        .kv td {
            padding: 2px 0;
            font-size: 7.6pt;
            border-bottom: none;
        }
        .kv .k { color: #333; }

        .tax {
            margin-top: 8px;
            border: 1px solid #6aa3cf;
        }
        .tax th {
            background: #d7ebf8;
            border: 1px solid #6aa3cf;
            font-size: 7pt;
            font-weight: bold;
            padding: 4px 5px;
            text-align: center;
        }
        .tax td {
            border: 1px solid #c5dcec;
            padding: 4px 6px;
            font-size: 7.5pt;
            text-align: center;
        }
        .tax td.l { text-align: left; }

        .leave { margin-top: 10px; }
        .leave-title { font-weight: bold; font-size: 8pt; margin: 0 0 4px; }
        .leave td { font-size: 7.5pt; padding: 2px 10px 2px 0; }

        .pay {
            border: 1.6px solid #4f92c7;
        }
        .pay td { vertical-align: middle; padding: 8px; }
        .pay .lbl {
            font-size: 9.5pt;
            font-weight: bold;
        }
        .pay .box {
            background: #d7ebf8;
            border: 1px solid #7eb3d9;
            text-align: right;
            padding: 8px 10px;
            font-size: 12.5pt;
            font-weight: bold;
        }

        .foot {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #c5dcec;
            font-size: 6.2pt;
            color: #555;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="co">
                    <table>
                        <tr>
                            <td>
                                <p class="co-name">{{ $b['company']['name'] !== '' ? $b['company']['name'] : 'Entreprise' }}</p>
                                @foreach($b['company']['address_lines'] as $line)
                                    <p class="co-line">{{ $line }}</p>
                                @endforeach
                                <p class="co-ids">
                                    @if($b['company']['id_number'] !== '')
                                        <strong>{{ $b['company']['id_label'] }} :</strong> {{ $b['company']['id_number'] }}
                                        &nbsp;&nbsp;
                                    @endif
                                    @if($b['company']['naf'] !== '')
                                        <strong>{{ $b['company']['naf_label'] }} :</strong> {{ $b['company']['naf'] }}
                                    @endif
                                </p>
                                @if($b['company']['org'] !== '')
                                    <p class="co-line"><strong>{{ $b['company']['org_label'] }} :</strong> {{ $b['company']['org'] }}</p>
                                @endif
                                @if($b['company']['convention'] !== '')
                                    <p class="co-line"><strong>Convention :</strong> {{ $b['company']['convention'] }}</p>
                                @endif
                            </td>
                            @if(!empty($b['company']['logo_src']))
                                <td style="width:78px; text-align:right; vertical-align:middle;">
                                    <img class="logo" src="{{ $b['company']['logo_src'] }}" alt="">
                                </td>
                            @endif
                        </tr>
                    </table>
                </div>
            </td>
            <td class="title-wrap">
                <p class="doc-title">Fiche de paie</p>
                <p class="period">Période du : <strong>{{ $b['period_start'] }}</strong> au : <strong>{{ $b['period_end'] }}</strong></p>
                <p class="period">Paiement le : <strong>{{ $b['payment_date'] }}</strong></p>
            </td>
        </tr>
    </table>

    <table class="who">
        <tr>
            <td class="meta">
                @if($b['employee']['matricule'] !== '')
                    <span class="k">Matricule :</span> {{ $b['employee']['matricule'] }}<br>
                @endif
                @if($b['employee']['ssn'] !== '')
                    <span class="k">N° Sécurité Sociale :</span> {{ $b['employee']['ssn'] }}<br>
                @endif
                @if($b['employee']['hired_at'] !== '')
                    <span class="k">Entrée le :</span> {{ $b['employee']['hired_at'] }}<br>
                @endif
                @if($b['employee']['job_title'] !== '')
                    <span class="k">Emploi :</span> {{ $b['employee']['job_title'] }}<br>
                @endif
                @if($b['employee']['qualification'] !== '')
                    <span class="k">Qualification :</span> {{ $b['employee']['qualification'] }}<br>
                @endif
                @if($b['employee']['coefficient'] !== '')
                    <span class="k">Coefficient :</span> {{ $b['employee']['coefficient'] }}<br>
                @endif
                @if($b['employee']['ss_ceiling'] !== '')
                    <span class="k">Plafond Sécurité Sociale :</span> {{ $b['employee']['ss_ceiling'] }}<br>
                @endif
                @if($b['employee']['contract_type'] !== '')
                    <span class="k">Contrat :</span> {{ $b['employee']['contract_type'] }}
                @endif
            </td>
            <td>
                <p class="emp-name">{{ $b['employee']['name'] }}</p>
                @foreach($b['employee']['address_lines'] as $line)
                    <p class="emp-addr">{{ $line }}</p>
                @endforeach
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th class="rub">Rubriques</th>
                <th class="w13">Base</th>
                <th class="w13">Taux salarial</th>
                <th class="w13">Cot. salariales</th>
                <th class="w13">Taux patronal</th>
                <th class="w13">Cot. patronales</th>
            </tr>
        </thead>
        <tbody>
            @foreach($b['rows'] as $row)
                @if($row['type'] === 'group')
                    <tr class="row-group">
                        <td class="rub" colspan="6">{{ $row['label'] }}</td>
                    </tr>
                @else
                    <tr class="{{ $row['style'] === 'brut' ? 'row-brut' : ($row['style'] === 'total' ? 'row-total' : ($row['bold'] ? 'row-bold' : '')) }}">
                        <td class="rub">{{ $row['label'] }}</td>
                        <td class="num">{{ $row['base'] }}</td>
                        <td class="num">{{ $row['employee_rate'] }}</td>
                        <td class="num">{{ $row['employee_amount'] }}</td>
                        <td class="num">{{ $row['employer_rate'] }}</td>
                        <td class="num">{{ $row['employer_amount'] }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="net-social">
                <td class="rub">MONTANT NET SOCIAL</td>
                <td></td>
                <td></td>
                <td class="num">{{ $b['net_social'] }}</td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="net-before">
        <tr>
            <td>NET À PAYER AVANT IMPÔT SUR LE REVENU</td>
            <td class="num amt">{{ $b['net_before_tax'] }}</td>
        </tr>
    </table>

    <table class="sum">
        <tr>
            <td>
                <table class="kv">
                    <tr><td class="k">Heures période</td><td class="num">{{ $b['hours']['period'] }}</td></tr>
                    <tr><td class="k">Cumul heures</td><td class="num">{{ $b['hours']['ytd'] }}</td></tr>
                    <tr><td class="k">Cumul heures sup.</td><td class="num">{{ $b['hours']['overtime'] }}</td></tr>
                </table>
            </td>
            <td>
                <table class="kv">
                    <tr><td class="k">Cumul bases</td><td class="num">{{ $b['cumul']['base'] }}</td></tr>
                    <tr><td class="k">Cumul brut</td><td class="num">{{ $b['cumul']['gross'] }}</td></tr>
                    <tr><td class="k">Cumul imposable</td><td class="num">{{ $b['cumul']['taxable'] }}</td></tr>
                </table>
            </td>
            <td>
                <table class="kv">
                    <tr><td class="k">Total cot. patronales</td><td class="num">{{ $b['cost']['employer'] }}</td></tr>
                    <tr><td class="k">Total des retenues</td><td class="num">{{ $b['cost']['deductions'] }}</td></tr>
                    <tr><td class="k">Coût global période</td><td class="num"><strong>{{ $b['cost']['global'] }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width:62%; padding-right:10px;">
                @if(!empty($b['income_tax']))
                    <table class="tax">
                        <thead>
                            <tr>
                                <th>Impôt sur le revenu</th>
                                <th>Base</th>
                                <th>Taux</th>
                                <th>Montant</th>
                                <th>Cumul annuel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="l">Montant net imposable</td>
                                <td class="num">{{ $b['income_tax']['base'] }}</td>
                                <td></td>
                                <td></td>
                                <td class="num">{{ $b['income_tax']['ytd_base'] }}</td>
                            </tr>
                            <tr>
                                <td class="l">{{ $b['income_tax']['label'] }}</td>
                                <td class="num">{{ $b['income_tax']['base'] }}</td>
                                <td>{{ $b['income_tax']['rate'] }}</td>
                                <td class="num">{{ $b['income_tax']['amount'] }}</td>
                                <td class="num">{{ $b['income_tax']['ytd_amount'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                <div class="leave">
                    <p class="leave-title">Congés Payés</p>
                    <table>
                        <tr>
                            <td>En cours<br><strong>{{ $b['leave']['current'] }}</strong></td>
                            <td>Acquis<br><strong>{{ $b['leave']['acquired'] }}</strong></td>
                            <td>Pris<br><strong>{{ $b['leave']['taken'] }}</strong></td>
                            <td>Solde<br><strong>{{ $b['leave']['balance'] }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width:38%; vertical-align:bottom;">
                <table class="pay">
                    <tr>
                        <td class="lbl">Net à payer<br>au salarié</td>
                        <td style="width:58%;">
                            <div class="box">{{ $b['net_to_pay'] }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="foot">
        Bulletin de paie simplifié — Conservez ce document sans limitation de durée.
        Document généré électroniquement
        @if(!empty($b['period_month']))
            pour {{ $b['period_month'] }}
        @endif.
    </p>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $payslip->employee_name }} - {{ $periodLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 24px 28px;
        }
        .header {
            border-bottom: 1px solid #d0d0d0;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 52px; max-width: 160px; }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 4px;
        }
        .company-meta { line-height: 1.5; color: #444; }
        .title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 16px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 2px 0;
        }
        .info-row-label {
            color: #555;
            width: 42%;
            display: inline-block;
        }
        .info-row-value {
            font-weight: 600;
        }
        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .amount-table th,
        .amount-table td {
            border: 1px solid #cfcfcf;
            padding: 7px 10px;
        }
        .amount-table th {
            background: #f3f3f3;
            text-align: left;
        }
        .amount-table .amount {
            text-align: right;
            width: 28%;
        }
        .amount-table .total-row td {
            font-weight: bold;
            background: #fafafa;
        }
        .summary {
            margin-top: 8px;
            line-height: 1.8;
        }
        .summary .grand-total {
            font-size: 14px;
            font-weight: bold;
            margin-top: 6px;
        }
        .footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #d0d0d0;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 30%;">
                    @if ($logoPath)
                        <img src="{{ $logoPath }}" alt="{{ $company?->name }}" class="logo">
                    @endif
                </td>
                <td style="width: 70%; text-align: right;">
                    <div class="company-name">{{ $company?->legal_name ?: $company?->name }}</div>
                    <div class="company-meta">
                        @if ($company?->full_address)
                            {{ $company->full_address }}<br>
                        @endif
                        @if ($company?->email)
                            {{ $company->email }}
                        @endif
                        @if ($company?->phone)
                            @if ($company?->email) | @endif{{ $company->phone }}
                        @endif
                        @if ($company?->website)
                            <br>{{ $company->website }}
                        @endif
                        @if ($company?->gstin)
                            <br>GSTIN: {{ $company->gstin }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">Payslip for the month of {{ $monthTitle }}</div>

    <table class="info-table">
        <tr>
            <td>
                <div><span class="info-row-label">Name:</span> <span class="info-row-value">{{ $payslip->employee_name }}</span></div>
                <div><span class="info-row-label">Employee ID:</span> <span class="info-row-value">{{ $payslip->employee_code ?: '—' }}</span></div>
                <div><span class="info-row-label">Designation:</span> <span class="info-row-value">{{ $payslip->designation ?: '—' }}</span></div>
                <div><span class="info-row-label">Department:</span> <span class="info-row-value">{{ $payslip->department_name ?: '—' }}</span></div>
                <div><span class="info-row-label">Location:</span> <span class="info-row-value">{{ $payslip->location ?: '—' }}</span></div>
                <div><span class="info-row-label">Date of Joining:</span> <span class="info-row-value">{{ $payslip->joining_date?->format('d M, Y') ?: '—' }}</span></div>
            </td>
            <td>
                <div><span class="info-row-label">Payable Days in Pay Period:</span> <span class="info-row-value">{{ number_format($payslip->payable_days, 1) }}</span></div>
                <div><span class="info-row-label">LOP Days in Pay Period:</span> <span class="info-row-value">{{ number_format($payslip->lop_days, 1) }}</span></div>
                <div><span class="info-row-label">Bank Name:</span> <span class="info-row-value">{{ $payslip->bank_name ?: '—' }}</span></div>
                <div><span class="info-row-label">Bank Account Number:</span> <span class="info-row-value">{{ $payslip->bank_account_number ?: '—' }}</span></div>
                <div><span class="info-row-label">PAN:</span> <span class="info-row-value">{{ $payslip->pan_number ?: 'None' }}</span></div>
                <div><span class="info-row-label">UAN:</span> <span class="info-row-value">{{ $payslip->uan ?: 'None' }}</span></div>
                <div><span class="info-row-label">PF Number:</span> <span class="info-row-value">{{ $payslip->pf_number ?: 'None' }}</span></div>
            </td>
        </tr>
    </table>

    @php
        $earningRows = $payslip->earnings ?? [];
        $deductionRows = $payslip->deductions ?? [];
        $maxRows = max(count($earningRows), count($deductionRows), 1);
    @endphp

    <table class="amount-table">
        <thead>
            <tr>
                <th colspan="2">Earnings</th>
                <th colspan="2">Deductions</th>
            </tr>
            <tr>
                <th>Component</th>
                <th class="amount">Amount</th>
                <th>Component</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                    <td>{{ $earningRows[$i]['label'] ?? '' }}</td>
                    <td class="amount">
                        @if (isset($earningRows[$i]['amount']))
                            ₹ {{ number_format($earningRows[$i]['amount'], 2) }}
                        @endif
                    </td>
                    <td>{{ $deductionRows[$i]['label'] ?? '' }}</td>
                    <td class="amount">
                        @if (isset($deductionRows[$i]['amount']))
                            ₹ {{ number_format($deductionRows[$i]['amount'], 2) }}
                        @endif
                    </td>
                </tr>
            @endfor
            <tr class="total-row">
                <td>Total Earnings</td>
                <td class="amount">₹ {{ number_format($payslip->total_earnings, 2) }}</td>
                <td>Total Deductions</td>
                <td class="amount">₹ {{ number_format($payslip->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <div><strong>Net Pay for the month:</strong> ₹ {{ number_format($payslip->net_pay, 2) }}</div>
        <div><strong>Expense Reimbursements:</strong> ₹ {{ number_format($payslip->expense_reimbursements, 2) }}</div>
        <div class="grand-total">Total Payable: {{ number_format($payslip->totalPayable(), 2) }}</div>
    </div>

    <div class="footer">
        This is a system generated payslip and does not require signature.
    </div>
</body>
</html>

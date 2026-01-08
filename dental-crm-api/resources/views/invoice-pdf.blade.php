<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рахунок {{ $invoice->invoice_number }}</title>
    <style>
        @charset "UTF-8";

        /* ---------- reset + base ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            max-height: 1000000px;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #000;
            line-height: 1.2;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* ---------- поля друку ---------- */
        @page {
            size: A4;
            margin: 20mm;          /* <-- регулюйте тут */
        }

        /* ---------- контейнер по центру ---------- */
        .container {
            max-width: 170mm;      /* 210 - 20*2 */
            margin: 0 auto;        /* центрування */
            box-sizing: border-box;
            position: relative;
        }

        .header { display:table; width:100%; margin-bottom:5px; border-bottom:1px solid #333; padding-bottom:3px; table-layout:fixed; }
        .header-left { display:table-cell; width:55%; vertical-align:top; padding-right:5px; }
        .header-right { display:table-cell; width:45%; text-align:right; vertical-align:top; padding-left:5px; }
        .logo { max-width:80px; max-height:40px; margin-bottom:3px; }
        .clinic-name { font-size:11px; font-weight:bold; margin-bottom:1px; line-height:1.1; }
        .clinic-slogan { font-size:7px; color:#666; margin-bottom:2px; line-height:1.1; }
        .clinic-contacts { font-size:6.5px; line-height:1.2; margin-top:1px; }
        .clinic-contacts > div { margin-bottom:0.5px; }
        .invoice-title { font-size:14px; font-weight:bold; margin-bottom:2px; letter-spacing:0.2px; line-height:1.1; }
        .invoice-number { font-size:8px; font-weight:600; margin-bottom:1px; color:#333; line-height:1.1; }
        .invoice-date { font-size:6.5px; color:#666; margin-top:0.5px; line-height:1.1; }

        .section { margin-bottom:5px; page-break-inside:avoid; }
        .section-title { font-size:8px; font-weight:bold; margin-bottom:3px; border-bottom:1px solid #ddd; padding-bottom:1px; color:#333; text-transform:uppercase; letter-spacing:0.1px; }
        .two-columns { display:table; width:100%; margin-bottom:4px; table-layout:fixed; }
        .column { display:table-cell; width:50%; vertical-align:top; padding-right:6px; }
        .column:last-child { padding-right:0; }
        .field-label { font-size:6px; color:#666; text-transform:uppercase; margin-bottom:0.3px; letter-spacing:0.1px; line-height:1.1; }
        .field-value { font-size:7px; margin-bottom:2px; font-weight:500; min-height:7px; line-height:1.1; hyphens:auto; }

        .bank-details { background:#f9f9f9; padding:3px; border:1px solid #ddd; border-radius:1px; font-size:6.5px; line-height:1.2; hyphens:auto; }
        .bank-details strong { display:block; margin-bottom:1px; font-size:7px; color:#333; }
        .bank-details > div { margin-bottom:0.5px; }

        .items-table-wrapper { width:100%; margin:0; padding:0; page-break-inside:avoid; }
        .items-table { width:100%; border-collapse:collapse; margin-bottom:5px; table-layout:fixed; font-size:7px; }
        .items-table th { background:#f5f5f5; border:1px solid #ddd; padding:1px; text-align:left; font-size:6px; font-weight:bold; text-transform:uppercase; line-height:1; hyphens:auto; }
        .items-table td { border:1px solid #ddd; padding:2px 1px; font-size:7px; vertical-align:middle; line-height:1; hyphens:auto; }
        .items-table tr:nth-child(even) { background:#fafafa; }
        .text-right { text-align:right; }
        .text-center { text-align:center; }

        .totals { margin-left:auto; margin-right:0; width:120px; max-width:100%; margin-bottom:5px; }
        .totals-row { display:table; width:100%; margin-bottom:1px; }
        .totals-label { display:table-cell; text-align:right; padding-right:5px; font-size:7px; }
        .totals-value { display:table-cell; text-align:right; font-size:7px; font-weight:bold; width:55px; white-space:nowrap; }
        .totals-row.total { margin-top:3px; padding-top:3px; border-top:1px solid #000; }
        .totals-row.total .totals-label,.totals-row.total .totals-value { font-size:8px; font-weight:bold; }

        .footer { margin-top:6px; padding-top:4px; border-top:1px solid #ddd; display:table; width:100%; table-layout:fixed; page-break-inside:avoid; }
        .footer-left { display:table-cell; width:50%; vertical-align:top; padding-right:5px; }
        .footer-right { display:table-cell; width:50%; text-align:right; vertical-align:bottom; padding-left:5px; }
        .signature-line { margin-top:20px; border-top:1px solid #000; width:120px; max-width:100%; padding-top:2px; font-size:7px; text-align:center; line-height:1.1; margin-left:auto; }

        /* друк – ваші стилі без змін */
        @media print {
            body { width:210mm; height:297mm; font-size:8px; }
            .container { width:170mm; padding:0; margin:0 auto; } /* підігнано під 170 мм */
            table,.header,.section,.footer { page-break-inside:avoid; }
            .items-table { font-size:6.5px; }
            .items-table td,.items-table th { padding:0.5px; }
            .invoice-title { font-size:13px; }
            .clinic-name { font-size:10px; }
            .section-title { font-size:7.5px; }
            .field-value,.bank-details { font-size:6.5px; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Шапка -->
    <div class="header no-break">
        <div class="header-left">
            @if($clinic->logo_url)
                @php
                    $logoPath = str_replace('/storage/', '', parse_url($clinic->logo_url, PHP_URL_PATH));
                    $fullLogoPath = storage_path('app/public/' . $logoPath);
                @endphp
                @if(file_exists($fullLogoPath))
                    @php
                        $imageData = file_get_contents($fullLogoPath);
                        $imageMime = mime_content_type($fullLogoPath);
                        $base64 = base64_encode($imageData);
                        $dataUri = 'data:' . $imageMime . ';base64,' . $base64;
                    @endphp
                    <img src="{{ $dataUri }}" alt="Logo" class="logo">
                @endif
            @endif
            <div class="clinic-name">{{ $clinic->name }}</div>
            @if($clinic->slogan)
                <div class="clinic-slogan">{{ $clinic->slogan }}</div>
            @endif
            <div class="clinic-contacts">
                @if(!empty($clinic->phone_main))
                    <div>{{ $clinic->phone_main }}</div>
                @endif
                @if(!empty($clinic->email_public))
                    <div>{{ $clinic->email_public }}</div>
                @endif
                @if(!empty($clinic->address_city) || !empty($clinic->address_street))
                    <div>
                        @if(!empty($clinic->address_city)){{ $clinic->address_city }}@endif
                        @if(!empty($clinic->address_city) && !empty($clinic->address_street)), @endif
                        @if(!empty($clinic->address_street)){{ $clinic->address_street }}@endif
                        @if(!empty($clinic->address_building)) {{ $clinic->address_building }}@endif
                    </div>
                @endif
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">РАХУНОК</div>
            <div class="invoice-number">№ {{ $invoice->invoice_number }}</div>
            <div class="invoice-date">Від {{ \Carbon\Carbon::parse($invoice->created_at)->format('d.m.Y') }}</div>
            @if($invoice->due_date)
                <div class="invoice-date" style="margin-top: 1px;">Оплатити до: {{ \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y') }}</div>
            @endif
        </div>
    </div>

    <!-- Блок "Отримувач" -->
    @php
        $requisites = is_array($clinic->requisites) ? $clinic->requisites : (is_string($clinic->requisites) ? json_decode($clinic->requisites, true) : []);
        $hasRequisites = !empty($requisites) && (isset($requisites['legal_name']) || isset($requisites['iban']) || $clinic->legal_name);
    @endphp
    @if($hasRequisites)
        <div class="section keep-together">
            <div class="section-title">Отримувач (Продавець)</div>
            <div class="two-columns">
                <div class="column">
                    @php
                        $legalName = $requisites['legal_name'] ?? $clinic->legal_name ?? '';
                    @endphp
                    @if(!empty($legalName))
                        <div class="field-label">Юридична назва</div>
                        <div class="field-value">{{ $legalName }}</div>
                    @endif
                    @php
                        $taxId = $requisites['tax_id'] ?? '';
                    @endphp
                    @if(!empty($taxId))
                        <div class="field-label">ЄДРПОУ / ІПН</div>
                        <div class="field-value">{{ $taxId }}</div>
                    @endif
                </div>
                <div class="column">
                    @php
                        $hasBankDetails = isset($requisites['iban']) || isset($requisites['bank_name']) || isset($requisites['mfo']);
                    @endphp
                    @if($hasBankDetails)
                        <div class="bank-details">
                            <strong>Банківські реквізити:</strong>
                            @if(!empty($requisites['iban']))
                                <div>IBAN: {{ $requisites['iban'] }}</div>
                            @endif
                            @if(!empty($requisites['bank_name']))
                                <div>Банк: {{ $requisites['bank_name'] }}</div>
                            @endif
                            @if(!empty($requisites['mfo']))
                                <div>МФО: {{ $requisites['mfo'] }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Блок "Платник" -->
    <div class="section keep-together">
        <div class="section-title">Платник (Покупник)</div>
        <div class="two-columns">
            <div class="column">
                @php
                    $patientName = $patient->full_name ?? '—';
                @endphp
                <div class="field-label">ПІБ</div>
                <div class="field-value">{{ $patientName }}</div>
            </div>
            <div class="column">
                @if(!empty($patient->phone))
                    <div class="field-label">Телефон</div>
                    <div class="field-value">{{ $patient->phone }}</div>
                @endif
                @if(!empty($patient->email))
                    <div class="field-label">Email</div>
                    <div class="field-value">{{ $patient->email }}</div>
                @endif
                @if(!empty($patient->address))
                    <div class="field-label">Адреса</div>
                    <div class="field-value">{{ $patient->address }}</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Таблиця послуг -->
    <div class="section no-break" style="margin-bottom: 6px;">
        <div class="items-table-wrapper">
            <table class="items-table">
                <thead>
                <tr>
                    <th class="text-center">№</th>
                    <th>Назва послуги</th>
                    <th class="text-center">Кільк.</th>
                    <th class="text-right">Ціна</th>
                    <th class="text-right">Сума</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td style="word-break: break-word; hyphens: auto;">{{ $item->name }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->price, 2, '.', ' ') }} грн</td>
                        <td class="text-right">{{ number_format($item->total, 2, '.', ' ') }} грн</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Підсумки -->
    <div class="totals no-break">
        @if($invoice->discount_amount > 0)
            <div class="totals-row">
                <div class="totals-label">Сума без знижки:</div>
                <div class="totals-value">{{ number_format($invoice->amount + $invoice->discount_amount, 2, '.', ' ') }} грн</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Знижка:</div>
                <div class="totals-value">-{{ number_format($invoice->discount_amount, 2, '.', ' ') }} грн</div>
            </div>
        @endif
        <div class="totals-row total">
            <div class="totals-label">Всього до сплати:</div>
            <div class="totals-value">{{ number_format($invoice->amount, 2, '.', ' ') }} грн</div>
        </div>
        @if($invoice->paid_amount > 0)
            <div class="totals-row">
                <div class="totals-label">Сплачено:</div>
                <div class="totals-value">{{ number_format($invoice->paid_amount, 2, '.', ' ') }} грн</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Залишок:</div>
                <div class="totals-value">{{ number_format($invoice->amount - $invoice->paid_amount, 2, '.', ' ') }} грн</div>
            </div>
        @endif
    </div>

    @if($invoice->description)
        <div class="section" style="margin-bottom: 3px;">
            <div class="field-label">Примітка</div>
            <div class="field-value" style="font-size: 7px;">{{ $invoice->description }}</div>
        </div>
    @endif

    <!-- Підвал -->
    <div class="footer keep-together">
        <div class="footer-left">
            <div style="font-size: 7px; color: #666; line-height: 1.3;">
                Дякуємо за Ваш вибір!<br>
                Бажаємо швидкого одужання!
            </div>
        </div>
        <div class="footer-right">
            <div class="signature-line">
                Підпис директора<br>
                (або уповноваженої особи)
            </div>
        </div>
    </div>
</div>
</body>
</html>

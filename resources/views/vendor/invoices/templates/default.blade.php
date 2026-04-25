<!DOCTYPE html>
<html lang="en">

<head>
    <title>{{ $invoice->name }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css" media="screen">
        * {
            font-family: "DejaVu Sans", sans-serif;
            margin: 0;
            padding: 0;
        }

        html {
            font-family: sans-serif;
            line-height: 1.15;
        }

        body {
            font-family: "DejaVu Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-weight: 400;
            line-height: 1.4;
            color: #1F2937;
            text-align: left;
            background-color: #F9FAFB;
            font-size: 9px;
            margin: 0;
            padding: 12pt;
        }

        .invoice-container {
            background: #FFFFFF;
            border-radius: 8px;
            padding: 16pt;
            max-width: 1000pt;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* Header Section */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 12pt;
            padding-bottom: 8pt;
            border-bottom: 2px solid #E5E7EB;
        }

        .logo-section {
            display: table-cell;
            width: 20%;
            padding-right: 20pt;
            vertical-align: top;
            line-height: 1;
        }

        .logo-section img {
            height: 50pt;
            width: auto;
            display: block;
            margin: 0;
            padding: 0;
            line-height: 0;
        }

        .invoice-header {
            display: table-cell;
            width: 80%;
            text-align: right;
            vertical-align: top;
        }

        .invoice-title {
            font-size: 22pt;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 2pt;
        }

        .invoice-status {
            font-size: 10pt;
            font-weight: 600;
            color: #10B981;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }

        .invoice-meta {
            margin-top: 6pt;
            font-size: 10pt;
        }

        .invoice-meta-item {
            display: block;
            margin-bottom: 2pt;
            color: #6B7280;
        }

        .invoice-meta-label {
            color: #6B7280;
        }

        .invoice-meta-value {
            color: #1F2937;
            font-weight: 600;
        }

        /* Party Section */
        .parties {
            margin-bottom: 12pt;
            width: 100%;
            border-collapse: collapse;
            display: table;
        }

        .party {
            display: table-cell;
            width: 48%;
            padding-right: 20pt;
            vertical-align: top;
        }

        .party:last-child {
            padding-right: 0;
        }

        .party-label {
            font-size: 10pt;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            margin-bottom: 6pt;
            border-bottom: 2px solid #3B82F6;
            padding-bottom: 4pt;
        }

        .party-content {
            font-size: 8.5pt;
            line-height: 1.4;
        }

        .party-name {
            font-size: 10pt;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 3pt;
        }

        .party-detail {
            color: #6B7280;
            margin-bottom: 2pt;
        }

        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
            font-size: 8.5pt;
            border-radius: 6pt;
            overflow: hidden;
            border: 1px solid #D1D5DB;
        }

        .table-items thead {}

        .table-items thead th {
            color: #1F2937;
            font-weight: 700;
            padding: 8pt;
            text-align: left;
            border: 1px solid #D1D5DB;
        }


        .table-items tbody tr {
            border-bottom: none;
        }

        .table-items tbody tr:hover {
            background-color: #F3F4F6;
        }

        .table-items tbody td {
            padding: 6pt 8pt;
            vertical-align: middle;
            border: 1px solid #D1D5DB;
        }


        .item-title {
            font-weight: 600;
            color: #1F2937;
        }

        .item-description {
            color: #6B7280;
            font-size: 7.5pt;
            margin-top: 1pt;
        }

        /* Summary Section */
        .summary-table {
            margin-left: auto;
            width: 40%;
            margin-bottom: 12pt;
        }

        .summary-table tr {
            border: none;
        }

        .summary-table td {
            padding: 4pt 8pt;
            border: none;
        }

        .summary-table .summary-label {
            text-align: right;
            color: #6B7280;
            font-weight: 500;
            font-size: 10pt;
        }

        .summary-table .summary-value {
            text-align: right;
            color: #1F2937;
            font-weight: 600;
            font-size: 10pt;
        }

        .summary-table .total-row {
            color: #1F2937;
            font-weight: 700;
            font-size: 10pt;
            border-top: 2px solid #3B82F6;
            border-bottom: 2px solid #3B82F6;
        }

        .summary-table .total-row td {
            padding: 8pt;
            border: none;
        }

        /* Footer Section */
        .footer-section {
            margin-top: 12pt;
            padding-top: 8pt;
            border-top: 2px solid #E5E7EB;
        }

        .footer-item {
            font-size: 10pt;
            color: #6B7280;
            margin-bottom: 3pt;
            line-height: 1.3;
        }

        .footer-label {
            font-weight: 600;
            color: #374151;
        }

        .footer-value {
            color: #1F2937;
        }

        .notes {
            background: #F9FAFB;
            padding: 8pt;
            border-left: 4px solid #3B82F6;
            margin-bottom: 8pt;
            font-size: 8pt;
            color: #6B7280;
        }

        /* Utility Classes */
        .border-0 {
            border: none !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .text-uppercase {
            text-transform: uppercase !important;
        }

        .pl-0 {
            padding-left: 0 !important;
        }

        .pr-0 {
            padding-right: 0 !important;
        }

        .px-0 {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .mt-5 {
            margin-top: 24pt !important;
        }

        p {
            margin-bottom: 8pt;
        }

        strong {
            font-weight: 600;
        }

        h4 {
            margin-bottom: 12pt;
            font-weight: 600;
            font-size: 13pt;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        {{-- Header Section --}}
        <div class="header">
            <div class="logo-section">
                @if ($invoice->logo)
                    <img src="{{ $invoice->getLogo() }}" alt="logo">
                @endif
            </div>
            <div class="invoice-header">
                <div class="invoice-title">INVOICE</div>
                @if ($invoice->status)
                    <div class="invoice-status">{{ $invoice->status }}</div>
                @endif
                <div class="invoice-meta">
                    <span class="invoice-meta-item">
                        <span class="invoice-meta-label">{{ __('invoices::invoice.serial') }}</span>
                        <span class="invoice-meta-value">{{ $invoice->getSerialNumber() }}</span>
                    </span>
                    <span class="invoice-meta-item">
                        <span class="invoice-meta-label">{{ __('invoices::invoice.date') }}</span>
                        <span class="invoice-meta-value">{{ $invoice->getDate() }}</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Parties Section --}}
        <div class="parties">
            <div class="party">
                <div class="party-label">{{ __('invoices::invoice.seller') }}</div>
                <div class="party-content">
                    @if ($invoice->seller->name)
                        <div class="party-name">{{ $invoice->seller->name }}</div>
                    @endif
                    @if ($invoice->seller->address)
                        <div class="party-detail">{{ $invoice->seller->address }}</div>
                    @endif
                    @if ($invoice->seller->code)
                        <div class="party-detail">{{ __('invoices::invoice.code') }}: {{ $invoice->seller->code }}
                        </div>
                    @endif
                    @if ($invoice->seller->vat)
                        <div class="party-detail">{{ __('invoices::invoice.vat') }}: {{ $invoice->seller->vat }}</div>
                    @endif
                    @if ($invoice->seller->phone)
                        <div class="party-detail">{{ __('invoices::invoice.phone') }}: {{ $invoice->seller->phone }}
                        </div>
                    @endif
                    @foreach ($invoice->seller->custom_fields as $key => $value)
                        <div class="party-detail">{{ ucfirst($key) }}: {{ $value }}</div>
                    @endforeach
                </div>
            </div>

            <div class="party">
                <div class="party-label">{{ __('invoices::invoice.buyer') }}</div>
                <div class="party-content">
                    @if ($invoice->buyer->name)
                        <div class="party-name">{{ $invoice->buyer->name }}</div>
                    @endif
                    @if ($invoice->buyer->address)
                        <div class="party-detail">{{ $invoice->buyer->address }}</div>
                    @endif
                    @if ($invoice->buyer->code)
                        <div class="party-detail">{{ __('invoices::invoice.code') }}: {{ $invoice->buyer->code }}
                        </div>
                    @endif
                    @if ($invoice->buyer->vat)
                        <div class="party-detail">{{ __('invoices::invoice.vat') }}: {{ $invoice->buyer->vat }}</div>
                    @endif
                    @if ($invoice->buyer->phone)
                        <div class="party-detail">{{ __('invoices::invoice.phone') }}: {{ $invoice->buyer->phone }}
                        </div>
                    @endif
                    @foreach ($invoice->buyer->custom_fields as $key => $value)
                        <div class="party-detail">{{ ucfirst($key) }}: {{ $value }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <table class="table table-items">
            <thead>
                <tr>
                    <th>{{ __('invoices::invoice.description') }}</th>
                    @if ($invoice->hasItemUnits)
                        <th class="text-center">{{ __('invoices::invoice.units') }}</th>
                    @endif
                    <th class="text-center">{{ __('invoices::invoice.quantity') }}</th>
                    <th>{{ __('invoices::invoice.price') }}</th>
                    @if ($invoice->hasItemDiscount)
                        <th>{{ __('invoices::invoice.discount') }}</th>
                    @endif
                    @if ($invoice->hasItemTax)
                        <th>{{ __('invoices::invoice.tax') }}</th>
                    @endif
                    <th>{{ __('invoices::invoice.sub_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td style="max-width: 150px">
                            <div class="item-title">{{ $item->title }}</div>
                            @if ($item->description)
                                <div class="item-description">{{ $item->description }}</div>
                            @endif
                        </td>
                        @if ($invoice->hasItemUnits)
                            <td class="text-center">{{ $item->units }}</td>
                        @endif
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td>{{ $invoice->formatCurrency($item->price_per_unit) }}</td>
                        @if ($invoice->hasItemDiscount)
                            <td>{{ $invoice->formatCurrency($item->discount) }}</td>
                        @endif
                        @if ($invoice->hasItemTax)
                            <td>{{ $invoice->formatCurrency($item->tax) }}</td>
                        @endif
                        <td>{{ $invoice->formatCurrency($item->sub_total_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Summary --}}
        <table class="summary-table">
            {{-- Discount --}}
            @if ($invoice->hasItemOrInvoiceDiscount())
                <tr>
                    <td class="summary-label">{{ __('invoices::invoice.total_discount') }}</td>
                    <td class="summary-value">{{ $invoice->formatCurrency($invoice->total_discount) }}</td>
                </tr>
            @endif

            {{-- Taxable Amount --}}
            @if ($invoice->taxable_amount)
                <tr>
                    <td class="summary-label">{{ __('invoices::invoice.taxable_amount') }}</td>
                    <td class="summary-value">{{ $invoice->formatCurrency($invoice->taxable_amount) }}</td>
                </tr>
            @endif

            {{-- Tax Rate --}}
            @if ($invoice->tax_rate)
                <tr>
                    <td class="summary-label">{{ __('invoices::invoice.tax_rate') }}</td>
                    <td class="summary-value">{{ $invoice->tax_rate }}%</td>
                </tr>
            @endif

            {{-- Taxes --}}
            @if ($invoice->hasItemOrInvoiceTax())
                <tr>
                    <td class="summary-label">{{ __('invoices::invoice.total_taxes') }}</td>
                    <td class="summary-value">{{ $invoice->formatCurrency($invoice->total_taxes) }}</td>
                </tr>
            @endif

            {{-- Shipping --}}
            @if ($invoice->shipping_amount)
                <tr>
                    <td class="summary-label">{{ __('invoices::invoice.shipping') }}</td>
                    <td class="summary-value">{{ $invoice->formatCurrency($invoice->shipping_amount) }}</td>
                </tr>
            @endif

            {{-- Total Amount --}}
            <tr class="total-row">
                <td class="summary-label">{{ __('invoices::invoice.total_amount') }}</td>
                <td class="summary-value">{{ $invoice->formatCurrency($invoice->total_amount) }}</td>
            </tr>
        </table>

        {{-- Footer Section --}}
        <div class="footer-section">
            @if ($invoice->notes)
                <div class="notes">
                    <span class="footer-label">{{ __('invoices::invoice.notes') }}:</span>
                    <br>{!! $invoice->notes !!}
                </div>
            @endif

            <div class="footer-item">
                <span class="footer-label">{{ __('invoices::invoice.amount_in_words') }}:</span>
                <span class="footer-value">{{ $invoice->getTotalAmountInWords() }}</span>
            </div>

            <div class="footer-item">
                <span class="footer-label">{{ __('invoices::invoice.pay_until') }}:</span>
                <span class="footer-value">{{ $invoice->getPayUntilDate() }}</span>
            </div>
        </div>
    </div>

    <script type="text/php">
            if (isset($pdf) && $PAGE_COUNT > 1) {
                $text = "{{ __('invoices::invoice.page') }} {PAGE_NUM} / {PAGE_COUNT}";
                $size = 10;
                $font = $fontMetrics->getFont("Verdana");
                $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
                $x = ($pdf->get_width() - $width);
                $y = $pdf->get_height() - 35;
                $pdf->page_text($x, $y, $text, $font, $size);
            }
        </script>
</body>

</html>

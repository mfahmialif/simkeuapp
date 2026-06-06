<table style="border-collapse: collapse;">
    <thead>
        <tr>
            <th colspan="6" style="background-color: #ffc000; color: #000000; text-align: center; font-weight: bold; border: 1px solid #000; height: 25px; vertical-align: middle;">
                {{ strtoupper($title) }}
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">NO</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">KATEGORI</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">TUNAI</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">TRANSFER</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">YAYASAN</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #ffffff; color: #000000;">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $row)
            <tr>
                <td style="border: 1px solid #000; text-align: center; background-color: #ffffff;">{{ $row['no'] }}</td>
                <td style="border: 1px solid #000; text-align: left; background-color: #ffffff;">{{ strtoupper($row['kategori']) }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($row['tunai_by_currency'] ?? []) }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($row['transfer_by_currency'] ?? []) }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($row['yayasan_by_currency'] ?? []) }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($row['total_by_currency'] ?? []) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2" style="border: 1px solid #000; text-align: center; font-weight: bold; font-style: italic; background-color: #ffffff;">TOTAL</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($totals['tunai_by_currency'] ?? []) }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($totals['transfer_by_currency'] ?? []) }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($totals['yayasan_by_currency'] ?? []) }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;">{{ \App\Services\MataUangFormatter::formatTotals($totals['total_by_currency'] ?? []) }}</th>
        </tr>
    </tfoot>
</table>

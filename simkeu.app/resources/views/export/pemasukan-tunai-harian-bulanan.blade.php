<table style="border-collapse: collapse;">
    <thead>
        <tr>
            <th colspan="{{ count($columns) + 3 }}" style="background-color: #ff0000; color: #ffffff; text-align: center; font-weight: bold; border: 1px solid #000; height: 25px; vertical-align: middle;">
                {{ strtoupper($title) }}
            </th>
        </tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">No</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">Tanggal</th>
            @foreach($columns as $col)
                <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">{{ strtoupper($col['label']) }}</th>
            @endforeach
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $index => $row)
            @php
                $bgColor = ($index % 2 === 0) ? '#d9e1f2' : '#ffffff';
            @endphp
            <tr>
                <td style="border: 1px solid #000; text-align: center; background-color: {{ $bgColor }};">{{ $row['no'] }}</td>
                <td style="border: 1px solid #000; text-align: center; background-color: {{ $bgColor }};">{{ date('d-m-y', strtotime($row['tanggal'])) }}</td>
                @foreach($columns as $col)
                    <td style="border: 1px solid #000; text-align: right; background-color: {{ $bgColor }};" data-format="&quot;Rp. &quot;#,##0">{{ $row[$col['key']] ?: '-' }}</td>
                @endforeach
                <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: {{ $bgColor }};" data-format="&quot;Rp. &quot;#,##0">{{ $row['jumlah'] ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2" style="border: 1px solid #000; text-align: center; font-weight: bold; background-color: #cccccc;">TOTAL</th>
            @foreach($columns as $col)
                <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #cccccc;" data-format="&quot;Rp. &quot;#,##0">{{ $totals[$col['key']] ?: '-' }}</th>
            @endforeach
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #cccccc;" data-format="&quot;Rp. &quot;#,##0">{{ $totals['jumlah'] ?: '-' }}</th>
        </tr>
    </tfoot>
</table>

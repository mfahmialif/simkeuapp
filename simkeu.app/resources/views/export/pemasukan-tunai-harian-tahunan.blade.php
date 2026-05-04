@foreach($allData as $monthData)
<table style="border-collapse: collapse;">
    <thead>
        <tr>
            <th></th> <!-- Column A spacing -->
            <th colspan="{{ count($columns) + 3 }}" style="background-color: #ff0000; color: #ffffff; text-align: center; font-weight: bold; border: 1px solid #000; height: 25px; vertical-align: middle;">
                {{ strtoupper($monthData['title']) }}
            </th>
        </tr>
        <tr>
            <th></th> <!-- Column A spacing -->
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">No</th>
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">Tanggal</th>
            @foreach($columns as $col)
                <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">{{ strtoupper($col['label']) }}</th>
            @endforeach
            <th style="font-weight: bold; border: 1px solid #000; text-align: center; background-color: #595959; color: #ffffff;">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @php
            $dataCount = count($monthData['data']);
        @endphp
        @foreach($monthData['data'] as $index => $row)
            @php
                $bgColor = ($index % 2 === 0) ? '#d9e1f2' : '#ffffff';
            @endphp
            <tr>
                @if($index === 0)
                    <td rowspan="{{ $dataCount + 1 }}" style="text-align: center; vertical-align: middle; font-weight: bold; font-size: 14px; border: 1px solid #000; background-color: #ffffff;">
                        {{ strtoupper($monthData['bulan_name']) }}
                    </td>
                @endif
                <td style="border: 1px solid #000; text-align: center; background-color: {{ $bgColor }};">{{ $row['no'] }}</td>
                <td style="border: 1px solid #000; text-align: center; background-color: {{ $bgColor }};">{{ date('d-m-y', strtotime($row['tanggal'])) }}</td>
                @foreach($columns as $col)
                    <td style="border: 1px solid #000; text-align: right; background-color: {{ $bgColor }};" data-format="&quot;Rp. &quot;#,##0">{{ $row[$col['key']] ?: '-' }}</td>
                @endforeach
                <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: {{ $bgColor }};" data-format="&quot;Rp. &quot;#,##0">{{ $row['jumlah'] ?: '-' }}</td>
            </tr>
        @endforeach
        <!-- TOTAL ROW -->
        <tr>
            <!-- Column A is covered by the rowspan from the first data row -->
            <th colspan="2" style="border: 1px solid #000; text-align: center; font-weight: bold; background-color: #cccccc;">TOTAL</th>
            @foreach($columns as $col)
                <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #cccccc;" data-format="&quot;Rp. &quot;#,##0">{{ $monthData['totals'][$col['key']] ?: '-' }}</th>
            @endforeach
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #cccccc;" data-format="&quot;Rp. &quot;#,##0">{{ $monthData['totals']['jumlah'] ?: '-' }}</th>
        </tr>
    </tbody>
    <tfoot>
        <!-- Two empty rows spacing between months -->
        <tr></tr>
        <tr></tr>
    </tfoot>
</table>
@endforeach

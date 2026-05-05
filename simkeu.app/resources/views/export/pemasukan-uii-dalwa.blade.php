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
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $row['tunai'] ?: 0 }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $row['transfer'] ?: 0 }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $row['yayasan'] ?: 0 }}</td>
                <td style="border: 1px solid #000; text-align: right; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $row['total'] ?: 0 }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2" style="border: 1px solid #000; text-align: center; font-weight: bold; font-style: italic; background-color: #ffffff;">TOTAL</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $totals['tunai'] ?: 0 }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $totals['transfer'] ?: 0 }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $totals['yayasan'] ?: 0 }}</th>
            <th style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #ffffff;" data-format="&quot;Rp &quot;* #,##0_ ;&quot;Rp &quot;* -_ ;&quot;Rp &quot;* &quot;-&quot;_ ;_ @_ ">{{ $totals['total'] ?: 0 }}</th>
        </tr>
    </tfoot>
</table>

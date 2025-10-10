<table>
    <thead>
        <tr>
            <th style="vertical-align: middle;font-weight:bold">LAPORAN PEMASUKAN KEUANGAN UII DALWA {{ $tahun }}
            </th>
        </tr>
        <tr>
            <th rowspan="2"
                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">NO
            </th>
            <th rowspan="2"
                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                SPP/PRODI</th>
            <th
                colspan="12"style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                BULAN</th>
            <th rowspan="2"
                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                JUMLAH</th>
        </tr>
        <tr>
            @foreach ($bulan as $namaBulan => $b)
                @if ($namaBulan != 'total')
                    <th
                        style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                        {{ $namaBulan }}</th>
                @endif
            @endforeach
        </tr>
    </thead>
    <tbody>
        @php
            $i = 1;
        @endphp
        @foreach ($prodi as $p)
            <tr>
                <td style="vertical-align: middle;border:1px solid #000;">
                    {{ $i++ }}
                </td>
                <td style="vertical-align: middle;border:1px solid #000;">
                    {{ $p->nama }}
                </td>
                {{-- TOtal Per Prodi --}}
                @foreach ($bulan as $namaBulan => $b)
                    @if ($namaBulan != 'total')
                        <td data-format='"Rp." #,##0_-'
                            style="text-align:right;vertical-align: middle;border:1px solid #000;">
                            {{ $b[$p->id] }}
                        </td>
                    @endif
                @endforeach
                <td data-format='"Rp." #,##0_-' style="text-align:right;vertical-align: middle;border:1px solid #000;">
                    {{ $bulan['total'][$p->id] }}
                </td>
            </tr>
        @endforeach

        {{-- TOTAL Per Bulan --}}
        <tr>
            <td colspan="2"
                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;border:1px solid #000;">
                TOTAL
            </td>
            @foreach ($bulan as $namaBulan => $b)
                @if ($namaBulan != 'total')
                    <td data-format='"Rp." #,##0_-'
                        style="background-color: #D9D9D9;text-align: right;border: 1px solid black;width: 100px">
                        {{ $b['total'] }}</td>
                @endif
            @endforeach

            {{-- Total semua per tahun --}}
            <td data-format='"Rp." #,##0_-'
                style="background-color: #D9D9D9;text-align:right;height:20px;font-weight:bold;vertical-align: middle;border:1px solid #000;">
                {{ $bulan['total']['total'] }}</td>
        </tr>
    </tbody>
</table>

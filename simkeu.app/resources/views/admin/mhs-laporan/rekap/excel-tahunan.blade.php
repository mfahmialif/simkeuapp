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
                colspan="{{ $jumlahBulan }}"style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                BULAN</th>
            <th rowspan="2"
                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                JUMLAH</th>
        </tr>
        <tr>
            @foreach ($tanggal as $bulan => $b)
                @if ($bulan != 'total')
                    <th
                        style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                        {{ $bulan }}</th>
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
                @foreach ($tanggal as $bulan => $b)
                    @if ($bulan != 'total')
                        <td data-format='"Rp." #,##0_-'
                            style="text-align:right;vertical-align: middle;border:1px solid #000;">
                            {{ $b['total'][$p->id] }}
                        </td>
                    @endif
                @endforeach
                <td data-format='"Rp." #,##0_-' style="text-align:right;vertical-align: middle;border:1px solid #000;">
                    {{ $tanggal['total'][$p->id] }}
                </td>
            </tr>
        @endforeach

        {{-- TOTAL Per Bulan --}}
        <tr>
            <td colspan="2"
                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;border:1px solid #000;">
                TOTAL
            </td>
            @foreach ($tanggal as $bulan => $bulan)
                @if ($bulan != 'total')
                    <td data-format='"Rp." #,##0_-'
                        style="background-color: #D9D9D9;text-align: right;border: 1px solid black;width: 100px">
                        {{ $tanggal[$bulan]['total']['total'] }}</td>
                @endif
            @endforeach

            {{-- Total semua per tahun --}}
            <td data-format='"Rp." #,##0_-'
                style="background-color: #D9D9D9;text-align:right;height:20px;font-weight:bold;vertical-align: middle;border:1px solid #000;">
                {{ $tanggal['total']['total'] }}</td>
        </tr>
    </tbody>
</table>

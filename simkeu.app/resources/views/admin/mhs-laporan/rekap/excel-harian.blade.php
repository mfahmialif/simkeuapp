<table>
    <tbody>
        @foreach ($tanggal as $bulan => $hari)
            @if ($bulan != 'total')
                <tr>
                    <td rowspan="{{ count($hari) - 1 + 3 }}"
                        style="vertical-align: middle;border: 1px solid black;font-weight: bold">
                        {{ $bulan }}
                    </td>
                    <td style="font-weight: bold">PEMBAYARAN BULAN {{ $bulan }} {{ $tahun }}
                    </td>
                </tr>
                <tr>
                    <td style="background-color: #595959;color: white;text-align: center;border: 1px solid black">No</td>
                    <td
                        style="background-color: #595959;color: white;text-align: center;border: 1px solid black;width: 100px">
                        Tanggal
                    </td>
                    @foreach ($prodi as $p)
                        <td
                            style="background-color: #595959;color: white;text-align: center;border: 1px solid black;width: 100px">
                            {{ $p->nama }}
                        </td>
                    @endforeach
                    <td
                        style="background-color: #595959;color: white;text-align: center;border: 1px solid black;width: 100px">
                        JUMLAH
                    </td>
                </tr>
                {{-- ISI --}}

                @for ($i = 1; $i <= count($hari) - 1; $i++)
                    <tr>
                        <td style="text-align: center;border: 1px solid black">{{ $i }}</td>
                        <td style="text-align: center;border: 1px solid black;width: 100px">{{ $hari[$i]['modif'] }}</td>

                        @foreach ($prodi as $p)
                            <td data-format='"Rp." #,##0_-'
                                style="text-align: right;border: 1px solid black;width: 100px">
                                {{ $tanggal[$bulan][$i]['transaksi'][$p->id] }}
                            </td>
                        @endforeach
                        <td data-format='"Rp." #,##0_-' style="text-align: right;border: 1px solid black;width: 100px">
                            {{ $tanggal[$bulan][$i]['transaksi']['total'] }}
                        </td>
                    </tr>
                @endfor
                {{-- TOTAL --}}
                <tr>
                    <td colspan="2" style="background-color: #D9D9D9;text-align: center;border: 1px solid black">
                        TOTAL
                    </td>

                    @foreach ($prodi as $p)
                        <td data-format='"Rp." #,##0_-'
                            style="text-align: right;border: 1px solid black;font-weight: bold;width: 100px">
                            {{ $tanggal[$bulan]['total'][$p->id] }}</td>
                    @endforeach
                    <td data-format='"Rp." #,##0_-'
                        style="text-align: right;border: 1px solid black;font-weight: bold;width: 100px">
                        {{ $tanggal[$bulan]['total']['total'] }}</td>
                </tr>
                <tr></tr>
                {{-- BREAK --}}
                <tr>
                    <td></td>
                    <td style="background-color: red" colspan="{{ count($prodi) + 3 }}"></td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

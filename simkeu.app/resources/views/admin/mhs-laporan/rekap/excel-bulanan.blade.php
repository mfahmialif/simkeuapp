@foreach ($tanggal as $bulan => $b)
    @if ($bulan != 'total')
        <table>
            <thead>
                {{-- KOP HEADER --}}
                <tr>
                    <th colspan="4" style="width:100px;font-weight: bold;text-align:center;">PEMASUKAN KEUANGAN
                        S1</th>
                </tr>
                <tr>
                    <th colspan="4" style="width:100px;font-weight: bold;text-align:center;">UNIVERSITAS ISLAM
                        INTERNASIOANAL
                        DARULLUGHAH WADDA'WAH</th>
                </tr>
                <tr>
                    <th colspan="4" style="width:100px;font-weight: bold;text-align:center;">BANGIL {{ $tahun }}
                    </th>
                </tr>
                <tr>
                </tr>

                {{-- TABLE HEADER --}}
                <tr>
                    <th rowspan="2"
                        style="font-weight: bold;width:150px;vertical-align: center;text-align:center;border:1px solid #000;">
                        NO
                    </th>
                    <th colspan="3"
                        style="font-weight: bold;vertical-align: center;text-align:center;border:1px solid #000;">BULAN
                        {{ $bulan }} {{ $tahun }}</th>
                </tr>
                <tr>
                    <th
                        style="font-weight: bold;width:150px;vertical-align: center;text-align:center;border:1px solid #000;">
                        KATEGORI
                    </th>
                    <th
                        style="font-weight: bold;width:150px;vertical-align: center;text-align:center;border:1px solid #000;">
                        NOMINAL</th>
                    <th
                        style="font-weight: bold;width:150px;vertical-align: center;text-align:center;border:1px solid #000;">
                        JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $i = 1;
                @endphp
                @foreach ($prodi as $p)
                    <tr>
                        <td style="vertical-align: middle;border:1px solid #000;text-align:center;">{{ $i++ }}
                        </td>
                        <td style="vertical-align: middle;border:1px solid #000;">{{ $p->nama }}</td>
                        <td data-format='"Rp." #,##0_-'
                            style="vertical-align: middle;border:1px solid #000;text-align:right;">
                            {{ $b['total'][$p->id] }}
                        </td>
                        <td data-format='"Rp." #,##0_-'
                            style="vertical-align: middle;border:1px solid #000;text-align:right;">
                            {{ $b['total'][$p->id] }}
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3"
                        style="background-color: #D9D9D9;font-weight: bold;width:150px;vertical-align: center;text-align:center;border:1px solid #000;">
                        TOTAL
                    </td>
                    <td data-format='"Rp." #,##0_-'
                        style="background-color: #D9D9D9;font-weight: bold;width:150px;vertical-align: center;text-align:right;border:1px solid #000;">
                        {{ $b['total']['total'] }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr></tr>
                <tr></tr>
                <tr></tr>
                <tr>
                    <th></th>
                    <th>Pengesahan</th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th></th>
                    <th>Warek II</th>
                    <th></th>
                    <th>Ka. Biro Keuangan</th>
                </tr>
                <tr></tr>
                <tr></tr>
                <tr></tr>
                <tr>
                    <th></th>
                    <th>Ust. Samsul Huda, M.Pd.I</th>
                    <th></th>
                    <th>Sayyid. Husin Ali, M.Pd</th>
                </tr>
                <tr></tr>
                <tr></tr>
                <tr></tr>
                <tr></tr>
            </tfoot>
        </table>
    @endif
@endforeach

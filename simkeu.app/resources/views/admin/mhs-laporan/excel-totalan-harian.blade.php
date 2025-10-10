{{-- Tanggal --}}
<table>
    <thead>
        <tr>
            <th colspan="4" style="width:100px;font-weight: bold;">Tanggal :
                {{ date('d/m/Y', strtotime($pilihTanggal)) }}</th>
        </tr>
    </thead>
</table>
{{-- Pemasukan Hari ini --}}
<table>
    @php
        $totalPemasukan = 0;
    @endphp
    @foreach ($getJenisPembayaran as $gjp)
        <thead>
            <tr>
                <th colspan="4" style="vertical-align: middle;width:100px;font-weight: bold;border:1px solid #000;">
                    {{ $gjp->nama }}</th>
            </tr>
            <tr>
                <th
                    style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000; background-color:#D9D9D9;">
                    NO
                </th>
                <th
                    style="width:200px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                    SPP/PRODI</th>
                <th
                    style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                    Jumlah</th>
                <th
                    style="width:200px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                    Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $i = 1;
                $totalPerBulan = 0;
                $totalDibayar = 0;
            @endphp
            @foreach ($jenisTagihan as $key => $jt)
                <tr>
                    <td style="vertical-align: middle;border:1px solid #000;">
                        {{ $i++ }}
                    </td>
                    <td style="vertical-align: middle;border:1px solid #000;">
                        {{ $jt->nama }}
                    </td>
                    @php
                        $jumlah = 0;
                        $jumlahDibayar = 0;
                    @endphp
                    @php
                        $jtId = $jt->id;
                        $transaksi = App\Models\KeuanganPembayaran::join(
                            'keuangan_tagihan as kt',
                            'kt.id',
                            '=',
                            'keuangan_pembayaran.tagihan_id',
                        )
                            ->leftJoin(
                                'keuangan_jenis_pembayaran_detail as kjpd',
                                'kjpd.pembayaran_id',
                                '=',
                                'keuangan_pembayaran.id',
                            )
                            ->whereDate('tanggal', '=', $pilihTanggal)
                            ->where([
                                ['kt.nama', 'LIKE', '%SPP%'],
                                ['kt.prodi_id', $jtId],
                                ['kjpd.jenis_pembayaran_id', $gjp->id],
                            ])
                            ->select('*', 'keuangan_pembayaran.jumlah as dibayar')
                            ->get();

                        $dataCek = (object) [
                            'id' => $jt->id,
                            'nama' => $jt->nama,
                        ];
                        if (in_array($dataCek, $tagihanSisa)) {
                            // Jika tagihan bukan SPP
                            $transaksi = App\Models\KeuanganPembayaran::join(
                                'keuangan_tagihan as kt',
                                'kt.id',
                                '=',
                                'keuangan_pembayaran.tagihan_id',
                            )
                                ->leftJoin(
                                    'keuangan_jenis_pembayaran_detail as kjpd',
                                    'kjpd.pembayaran_id',
                                    '=',
                                    'keuangan_pembayaran.id',
                                )
                                ->whereDate('tanggal', '=', $pilihTanggal)
                                ->where([['kt.nama', 'LIKE', "%$jt->id%"], ['kjpd.jenis_pembayaran_id', $gjp->id]])
                                ->select('*', 'keuangan_pembayaran.jumlah as dibayar')
                                ->get();
                        }

                        foreach ($transaksi as $tr) {
                            $jumlahDibayar += $tr->dibayar;
                            $totalDibayar += $tr->dibayar;
                        }

                        $totalPerBulan += $transaksi->count();
                    @endphp
                    <td style="text-align:right;vertical-align: middle;border:1px solid #000;">
                        {{ $transaksi->count() }}
                    </td>
                    <td data-format='"Rp." #,##0_-'
                        style="width:200px;text-align:right;vertical-align: middle;border:1px solid #000;">
                        {{ $jumlahDibayar }}
                    </td>
                </tr>
            @endforeach
            {{-- TOTAL Per Bulan --}}
            <tr>
                <td colspan="2"
                    style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                    TOTAL
                </td>
                <td
                    style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                    {{ $totalPerBulan }}
                </td>
                <td data-format='"Rp." #,##0_-'
                    style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                    {{ $totalDibayar }}
                </td>
            </tr>
            <tr>
                <td colspan="4"
                    style="background-color: #yellow;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                </td>
            </tr>
        </tbody>
        @php
            $totalPemasukan += $totalDibayar;
        @endphp
    @endforeach
</table>
{{-- Total Pemasukan Hari ini --}}
<table>
    <tbody>
        <tr>
            <td colspan="3"
                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                TOTAL PEMASUKAN HARI INI
            </td>
            <td data-format='"Rp." #,##0_-'
                style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                {{ $totalPemasukan }}
            </td>
        </tr>
    </tbody>
</table>
{{-- Pengeluaran --}}
<table>
    <tbody>
        @if ($rowspan != null)
            <td rowspan="{{ $rowspan }}" colspan="2"
                style="width:50px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                KETERANGAN</td>
        @else
            <td colspan="2"
                style="width:50px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;">
                KETERANGAN</td>
            <td colspan="2" style="width:100px;vertical-align: middle;text-align:right;border:1px solid #000;">
                Tidak Ada Pengeluaran Hari Ini</td>
        @endif
        @php
            $totalPengeluaran = 0;
        @endphp
        @foreach ($setoran as $item)
            <tr>
                <td style="width:100px;vertical-align: middle;text-align:center;border:1px solid #000;">
                    {{ $item->keterangan }}</td>
                <td data-format='"Rp." #,##0_-'
                    style="width:200px;vertical-align: middle;text-align:right;border:1px solid #000;">
                    {{ $item->jumlah }}</td>
            </tr>
            {{ $totalPengeluaran += $item->jumlah }}
        @endforeach
    </tbody>
</table>
{{-- Total Saldo --}}
<table>
    <tbody>
        <tr>
            <td colspan="3"
                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                TOTAL PENGELUARAN HARI INI
            </td>
            <td data-format='"Rp." #,##0_-'
                style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                {{ $totalPengeluaran }}
            </td>
        </tr>
        <tr></tr>
        <tr>
            <td colspan="3"
                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                TOTAL SALDO HARI INI
            </td>
            <td data-format='"Rp." #,##0_-'
                style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                {{ $totalSaldo = $totalPemasukan - $totalPengeluaran }}
            </td>
        </tr>
    </tbody>
</table>


@foreach ($jenisKelamin as $jk)
    @if ($jp->kode == $jk->kode || $jp->kode == '%')
        {{-- DATA TAMBAHAN PEMBAYARAN --}}
        <br>
        <tr>
            <td colspan="4"
                style="background-color: blue;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
            </td>
        </tr>
        <tr>
            <td colspan="4"
                style="height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                PEMBAYARAN
                TAMBAHAN {{ strtoupper($jk->nama) }}
            </td>
        </tr>
        <tr>
            <td colspan="4"
                style="background-color: blue;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
            </td>
        </tr>
        {{-- Pemasukan Hari ini --}}
        <table>
            @php
                $totalPemasukan = 0;
            @endphp
            @foreach ($getJenisPembayaran as $gjp)
                @if (\Str::contains($gjp->kategori, $jk->kategori))
                    <thead>
                        <tr>
                            <th colspan="4"
                                style="vertical-align: middle;width:100px;font-weight: bold;border:1px solid #000;">
                                {{ $gjp->nama }}</th>
                        </tr>
                        <tr>
                            <th
                                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000; background-color:#D9D9D9;">
                                NO
                            </th>
                            <th
                                style="width:200px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                                SPP/PRODI</th>
                            <th
                                style="width:100px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                                Jumlah</th>
                            <th
                                style="width:200px;vertical-align: middle;font-weight:bold;text-align:center;border:1px solid #000;background-color:#D9D9D9;">
                                Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 1;
                            $totalPerBulan = 0;
                            $totalDibayar = 0;
                        @endphp
                        @foreach ($jenisTagihanPembayaranTambahan[$jk->kode] as $key => $jt)
                            <tr>
                                <td style="vertical-align: middle;border:1px solid #000;">
                                    {{ $i++ }}
                                </td>
                                <td style="vertical-align: middle;border:1px solid #000;">
                                    {{ $jt->nama }}
                                </td>
                                @php
                                    $jumlah = 0;
                                    $jumlahDibayar = 0;
                                @endphp
                                @php
                                    $transaksi = App\Models\KeuanganPembayaranTambahan::whereDate(
                                        'tanggal',
                                        '=',
                                        $pilihTanggal,
                                    )
                                        ->where([
                                            ['tagihan', 'LIKE', '%SPP%'],
                                            ['prodi', $jt->nama],
                                            ['jenis_pembayaran', $gjp->nama],
                                            ['jenis_kelamin', $jk->kode],
                                        ])
                                        ->get();

                                    $dataCek = (object) [
                                        'id' => $jt->id,
                                        'nama' => $jt->nama,
                                        'semester' => false,
                                    ];

                                    if (in_array($dataCek, $tagihanSisaPembayaranTambahan[$jk->kode])) {
                                        // Jika tagihan bukan SPP
                                        $transaksi = App\Models\KeuanganPembayaranTambahan::whereDate(
                                            'tanggal',
                                            '=',
                                            $pilihTanggal,
                                        )
                                            ->where([
                                                ['tagihan', '=', "$jt->id"],
                                                ['jenis_pembayaran', $gjp->nama],
                                                ['jenis_kelamin', $jk->kode],
                                            ])
                                            ->get();
                                    }

                                    $dataCek = (object) [
                                        'id' => $jt->id,
                                        'nama' => $jt->nama,
                                        'semester' => true,
                                    ];

                                    if (in_array($dataCek, $tagihanSisaPembayaranTambahan[$jk->kode])) {
                                        // Jika tagihan bukan SPP dan ada semesternya
                                        $transaksi = App\Models\KeuanganPembayaranTambahan::whereDate(
                                            'tanggal',
                                            '=',
                                            $pilihTanggal,
                                        )
                                            ->where([
                                                ['tagihan', 'LIKE', "%$jt->id%"],
                                                ['jenis_pembayaran', $gjp->nama],
                                                ['jenis_kelamin', $jk->kode],
                                            ])
                                            ->get();
                                    }

                                    foreach ($transaksi as $tr) {
                                        $jumlahDibayar += $tr->bayar;
                                        $totalDibayar += $tr->bayar;
                                    }

                                    $totalPerBulan += $transaksi->count();
                                @endphp
                                <td style="text-align:right;vertical-align: middle;border:1px solid #000;">
                                    {{ $transaksi->count() }}
                                </td>
                                <td data-format='"Rp." #,##0_-'
                                    style="width:200px;text-align:right;vertical-align: middle;border:1px solid #000;">
                                    {{ $jumlahDibayar }}
                                </td>
                            </tr>
                        @endforeach
                        {{-- TOTAL Per Bulan --}}
                        <tr>
                            <td colspan="2"
                                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                                TOTAL
                            </td>
                            <td
                                style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                                {{ $totalPerBulan }}
                            </td>
                            <td data-format='"Rp." #,##0_-'
                                style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                                {{ $totalDibayar }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4"
                                style="background-color: blue;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                            </td>
                        </tr>
                    </tbody>
                    @php
                        $totalPemasukan += $totalDibayar;
                    @endphp
                @endif
            @endforeach
        </table>
        <br>
        {{-- Total Pemasukan Hari ini --}}
        <table>
            <tbody>
                <tr>
                    <td colspan="3"
                        style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                        TOTAL PEMASUKAN HARI INI
                    </td>
                    <td data-format='"Rp." #,##0_-'
                        style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                        {{ $totalPemasukan }}
                    </td>
                </tr>
            </tbody>
        </table>
        {{-- Total Saldo --}}
        <table>
            <tbody>
                <tr>
                    <td colspan="3"
                        style="background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: center;border:1px solid #000;">
                        TOTAL SALDO HARI INI
                    </td>
                    <td data-format='"Rp." #,##0_-'
                        style="width:200px;background-color: #D9D9D9;height:20px;font-weight:bold;vertical-align: middle;text-align: right;border:1px solid #000;">
                        {{ $totalPemasukan }}
                    </td>
                </tr>
            </tbody>
        </table>
    @endif
@endforeach

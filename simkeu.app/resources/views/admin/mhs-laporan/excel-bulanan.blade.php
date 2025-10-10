<table>
    <thead>
        {{-- HEADER KOP --}}
        <tr>
            <th colspan="7" style="text-align: center">U N I V E R S I T A S I S L A M I N T E R N A S I O N A L</th>
        </tr>
        <tr>
            <th colspan="7" style="text-align: center">D A R U L L U G H A H W A D D A ' W A H</th>
        </tr>
        <tr>
            <th colspan="7" style="text-align: center">R A C I B A N G I L P A S U R U A N J A W A T I M U R</th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="7" style="border-top: 3px solid #000;"></th>
        </tr>
        <tr>
            <th colspan="7" style="text-align: center">Jl. Raya Raci No.51 PO.Box.8 Bangil, Telp. (0343) 745317</th>
        </tr>
        <tr>
            <th colspan="7" style="border-bottom: 3px solid #000;"></th>
        </tr>
        <tr>
            <th colspan="7">LAPORAN BULANAN</th>
        </tr>
        <tr>
        </tr>
        <tr>
            <th colspan="2">Bulan : {{ $pilihBulan }}</th>
        </tr>
        <tr>
            <th colspan="2">Kategori : {{ $kategori }}</th>
        </tr>
        <tr>
            <th colspan="2">Jenis Pembayaran : {{ $jenisPembayaran }}</th>
        </tr>
        <tr></tr>

        {{-- HEAD UNTUK KOLOM --}}
        <tr>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border-bottom:2px solid #000">
                No.</th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                Nota</th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                NIM/No.Pendaftaran
            </th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                Prodi</th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                Jenis Pembayaran</th>
            <th colspan="2"
                style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border-bottom:2px solid #000">
                Sub Total</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalPemasukan1 = 0;
            $totalPemasukan2 = 0;
        @endphp
        @php
            $i = 1;
            $total = 0;
        @endphp
        @foreach ($dataPembayaran as $item)
            @php
                if ($item->dibayar == $item->nim) {
                    $item->dibayar = $item->jumlah_tagihan;
                }
                $total += $item->dibayar;
            @endphp
            <tr>
                <td style="vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                    {{ $i++ }}</td>
                <td
                    style="vertical-align: middle;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    {{ $item->nomor }}</td>
                <td data-format="0"
                    style="vertical-align: middle;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    {{ $item->nim }}</td>
                <td
                    style="vertical-align: middle;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    {{ $item->tagihan->prodi->nama }}</td>
                <td
                    style="vertical-align: middle;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    {{ $item->kjp_nama }}</td>
                <td colspan="2" data-format='"Rp." #,##0_-'
                    style="vertical-align: middle;height:40px;text-align:right;border-bottom:2px solid #000">
                    {{ $item->dibayar }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5"
                style="font-weight:bold;vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Total</td>
            <td colspan="2" data-format='"Rp." #,##0_-'
                style="vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                {{ $total }}</td>
        </tr>
        @php
            $totalPemasukan1 += $total;
        @endphp
        {{-- Hitung Total Pemasukan --}}
        @php
            $totalPemasukan = $totalPemasukan1 + $totalPemasukan2;
        @endphp
        <tr>
            <td colspan="5"
                style="font-weight:bold;vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Total Pemasukan</td>
            <td data-format='"Rp." #,##0_-' colspan="2"
                style="vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                {{ $totalPemasukan }}</td>
        </tr>

        {{-- Hitung Total Pengeluaran --}}
        <tr></tr>
        <tr>
            <td
                style="font-weight:bold;vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                No</td>
            <td colspan="4"
                style="font-weight:bold;vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                Pengeluaran</td>
            <td colspan="2"
                style="font-weight:bold;vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                Jumlah</td>
        </tr>
        @php
            $i = 1;
            $totalPengeluaran = 0;
            $setoran = DB::table('keuangan_setoran')
                ->whereMonth('tanggal', '=', $pilihBulan)
                ->where([['status', 'setuju'], ['kategori', 'LIKE', "%$jp->kategori%"]])
                ->get();
        @endphp
        @foreach ($setoran as $item)
            <tr>
                <td style="vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                    {{ $i++ }}</td>
                <td colspan="4"
                    style="vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                    {{ $item->keterangan }}</td>
                <td colspan="2" data-format='"Rp." #,##0_-'
                    style="vertical-align: middle;height:40px;text-align:right;border-bottom:2px solid #000">
                    {{ $item->jumlah }}</td>
            </tr>
            @php
                $totalPengeluaran += $item->jumlah;
            @endphp
        @endforeach
        <tr>
            <td colspan="5"
                style="font-weight:bold;vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Total Pengeluaran</td>
            <td data-format='"Rp." #,##0_-' colspan="2"
                style="vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                {{ $totalPengeluaran }}</td>
        </tr>
        {{-- Hitung Total Keseluruhan --}}
        <tr></tr>
        @php
            $totalKeseluruhan = $totalPemasukan - $totalPengeluaran;
        @endphp
        <tr>
            <td colspan="5"
                style="font-weight:bold;vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Total Keseluruhan</td>
            <td data-format='"Rp." #,##0_-' colspan="2"
                style="vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                {{ $totalKeseluruhan }}</td>
        </tr>
    </tbody>
</table>

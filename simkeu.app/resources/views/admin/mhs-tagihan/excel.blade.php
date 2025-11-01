<table>
    <thead>
        {{-- HEADER KOP --}}
        <tr>
            <th colspan="5" style="text-align: center">U N I V E R S I T A S I S L A M I N T E R N A S I O N A L</th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center">D A R U L L U G H A H W A D D A ' W A H</th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center">R A C I B A N G I L P A S U R U A N J A W A T I M U R</th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="5" style="border-top: 3px solid #000;"></th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center">Jl. Raya Raci No.51 PO.Box.8 Bangil, Telp. (0343) 745317</th>
        </tr>
        <tr>
            <th colspan="5" style="border-bottom: 3px solid #000;"></th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center">LAPORAN TUNGGAKAN</th>
        </tr>
        <tr>
        </tr>
        <tr>
            <th colspan="3">Tanggal : {{ $tanggal }}</th>
        </tr>
        <tr>
            <th colspan="3">NIM : {{ $nim }} ({{ $nama }}) </th>
        </tr>
        <tr>
            <th colspan="3">Prodi : {{ $prodi }}</th>
        </tr>
        <tr>
            <th colspan="3">Tahun Akademik : {{ $tahun_akademik }}</th>
        </tr>
        <tr>
            <th colspan="3">Deposit: Rp. {{ $deposit }}</th>
        </tr>
        <tr></tr>

        {{-- HEAD UNTUK KOLOM --}}
        <tr>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border-bottom:2px solid #000">
                No.</th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:300px;text-align:center;border-bottom:2px solid #000">
                Jenis Pembayaran</th>
            <th
                style="font-weight:bold;vertical-align: center;height:40px;width:300px;text-align:center;border-bottom:2px solid #000">
                Keterangan
            </th>
            <th colspan="2"
                style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border-bottom:2px solid #000">
                Sub Total (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $total = 0;
        @endphp
        @if ($status)
            @php
                $i = 1;
            @endphp
            @foreach ($data as $t)
                @php
                    $dibayar = $t->dibayar > 0 ? " (dibayar Rp. $t->dibayar)" : '';
                    $dispensasi = $t->status_dispensasi && $t->jenis_dispensasi != "Beasiswa"
                        ? " (dispensasi ($t->jenis_dispensasi) Rp. $t->jumlah_dispensasi)"
                        : '';
                    $status = $t->sisa > 0 ? 'BELUM LUNAS' : 'LUNAS';
                    $keterangan = $status . $dibayar . $dispensasi;
                    $subTotal = $t->sisa;

                @endphp
                <tr>
                    <td style="vertical-align: middle;height:40px;text-align:center;border-bottom:2px solid #000">
                        {{ $i++ }}</td>
                    <td
                        style="vertical-align: middle;height:40px;width:300px;text-align:center;border-bottom:2px solid #000">
                        {{ $t->nama }}</td>
                    <td
                        style="vertical-align: middle;height:40px;width:300px;text-align:center;border-bottom:2px solid #000">
                        {{ $keterangan }}</td>
                    <td colspan="2"
                        style="vertical-align: middle;height:40px;text-align:right;border-bottom:2px solid #000">
                        {{ $subTotal }}</td>
                </tr>
                @php

                    $total += $subTotal;

                @endphp
            @endforeach
        @endif
        {{-- Hitung Total Keseluruhan --}}
        <tr>
            <td colspan="3"
                style="font-weight:bold;vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Total Keseluruhan</td>
            <td colspan="2" style="vertical-align: middle;height:20px;text-align:right;border-bottom:2px solid #000">
                Rp. {{ number_format($total, '0', ',', '.') }}</td>
        </tr>
    </tbody>
</table>

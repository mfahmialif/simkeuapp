<table>
    <thead>
        <tr>
            <th colspan="12" style="text-align: center; font-weight: bold;">UNIVERSITAS ISLAM INTERNASIONAL</th>
        </tr>
        <tr>
            <th colspan="12" style="text-align: center; font-weight: bold;">DARULLUGHAH WADDA'WAH</th>
        </tr>
        <tr>
            <th colspan="12" style="text-align: center;">RACI BANGIL PASURUAN JAWA TIMUR Jl. Raya Raci No.51 PO.Box.8 Bangil, Telp. (0343) 745317</th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="12" style="text-align: center; font-weight: bold;">LAPORAN HARIAN</th>
        </tr>
        <tr></tr>
        <tr>
            <th>Tanggal Pelayanan</th>
            <th colspan="11">: {{ date('d-m-Y', strtotime($data['tanggal'])) }}</th>
        </tr>
        <tr>
            <th>Kategori</th>
            <th colspan="11">: {{ $data['kategori'] }}</th>
        </tr>
        <tr></tr>
        <tr>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">No</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Tanggal</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Tgl.Trans</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Kwitansi</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">NIM/NoDaftar</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Nama</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">L/P</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Prodi</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Pembayaran</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Nominal</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Metode</th>
            <th style="font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;">Petugas</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['rows'] as $row)
            <tr>
                <td style="text-align:center;">{{ $row['no'] }}</td>
                <td style="text-align:center;">{{ $row['tanggal_input'] ? date('d/m/Y', strtotime($row['tanggal_input'])) : '-' }}</td>
                <td style="text-align:center;">{{ $row['tanggal_transaksi'] ? date('d/m/Y', strtotime($row['tanggal_transaksi'])) : '-' }}</td>
                <td style="text-align:center;">{{ $row['kwitansi'] }}</td>
                <td style="text-align:center;">{{ $row['nim'] }}</td>
                <td>{{ $row['nama'] }}</td>
                <td style="text-align:center;">{{ $row['jenis_kelamin'] }}</td>
                <td style="text-align:center;">{{ $row['prodi'] }}</td>
                <td>{{ $row['pembayaran'] }}</td>
                <td style="text-align:right;">{{ \App\Services\MataUangFormatter::amount($row['nominal'], $row['mata_uang']) }}</td>
                <td style="text-align:center;">{{ $row['metode'] }}</td>
                <td>{{ $row['petugas'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="9" style="font-weight:bold;text-align:right;border-top:1px solid #000;">TOTAL:</td>
            <td style="font-weight:bold;text-align:right;border-top:1px solid #000;">{{ \App\Services\MataUangFormatter::formatTotals($data['total_by_currency'] ?? []) }}</td>
            <td colspan="2" style="border-top:1px solid #000;"></td>
        </tr>
    </tbody>
</table>

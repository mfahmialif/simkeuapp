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
        <tr></tr>
        <tr></tr>
        <tr>
            <th colspan="7" style="text-align: center;">Mahasiswa yang Sudah dan Belum Bayar</th>
        </tr>
    </thead>
</table>
@foreach ($jkId as $jk)
    <table>
        <thead>

            <tr></tr>
            <tr>
                @php
                    $jenisKelamin = $jk == 8 ? 'Putra' : 'Putri';
                @endphp
                <th colspan="7"
                    style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border:2px solid #000">
                    {{ $jenisKelamin }}</th>
            </tr>
            <tr>
            </tr>
            {{-- HEAD UNTUK KOLOM --}}
            <tr>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;text-align:center;border-bottom:2px solid #000">
                    No.</th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Tahun Akademik</th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Prodi
                </th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Semester
                </th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Jumlah Sudah Bayar</th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Jumlah Belum Bayar</th>
                <th
                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                    Jumlah Mahasiswa</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
            @endphp
            @foreach ($data[$jk] as $ta => $valData)
                @if ($ta != 'total')
                    @foreach ($valData as $prodi => $valProdi)
                        @foreach ($valProdi as $semester => $v)
                            <tr>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $no++ }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $ta }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $prodi }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $semester }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $v['sudah_bayar'] }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $v['belum_bayar'] }}</td>
                                <td
                                    style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                    {{ $v['mahasiswa'] }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="4"
                                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:right;border-bottom:2px solid #000">
                                Total per Prodi</td>
                            <td
                                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                {{ $data[$jk]['total'][$ta][$prodi]['sudah_bayar'] }}</td>
                            <td
                                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                {{ $data[$jk]['total'][$ta][$prodi]['belum_bayar'] }}</td>
                            <td
                                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                                {{ $data[$jk]['total'][$ta][$prodi]['mahasiswa'] }}</td>
                        </tr>
                        <tr>
                            <td colspan="7"
                                style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border:2px solid #1198d6; background-color:#efff5f ;">
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="4"
                            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:right;border-bottom:2px solid #000">
                            Total per Tahun Akademik</td>

                        <td
                            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                            {{ $data[$jk]['total'][$ta]['total']['sudah_bayar'] }}</td>
                        <td
                            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                            {{ $data[$jk]['total'][$ta]['total']['belum_bayar'] }}</td>
                        <td
                            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border-bottom:2px solid #000">
                            {{ $data[$jk]['total'][$ta]['total']['mahasiswa'] }}</td>
                    </tr>
                    <tr>
                        <td colspan="7"
                            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border:2px solid #1198d6; background-color:#ff8e0c ;">
                        </td>
                    </tr>
                @endif
            @endforeach

        </tbody>
    </table>
    <tr>
        <td colspan="7"
            style="font-weight:bold;vertical-align: center;height:40px;width:170px;text-align:center;border:2px solid #1198d6; background-color:#1198d6 ;">
        </td>
    </tr>
@endforeach

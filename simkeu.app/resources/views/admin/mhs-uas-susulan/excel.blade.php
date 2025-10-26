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
        </tr>
        <tr>
            <th colspan="7" style="text-align: center">Tanggal : {{ \Carbon\Carbon::create($tanggal)->format('d-m-Y') }}</th>
        </tr>
        <tr></tr>
    </thead>
</table>

@foreach ($prodi as $p)
    <table>
        <tr>
            <td colspan="7"
                style="text-align: center; background-color: #000000; color: #FFFFFF; font-weight: bold;">
                Prodi: {{ $p->nama }}
            </td>

        </tr>
    </table>
    @foreach ($dataExcel as $keyDataExcelProdi => $dataExcelProdi)
        @php
            if ($keyDataExcelProdi != $p->id) {
                continue;
            }
        @endphp
        @foreach ($dataExcelProdi as $keyDataExcelMk => $dataExcelMk)
            <table>
                <thead>
                    <tr>
                        <td colspan="7" style="text-align: center">MK: {{ @$dataExcelMk[0]['mk_nama'] }}</td>
                    </tr>
                    <tr>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">No</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">NIM</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">Nama</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">L/P</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">Prodi</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">Matakuliah</th>
                        <th style="text-align: center;border: 1px solid black;background-color:gray">Kelompok</th>
                    </tr>
                </thead>
                <tbody>

                    @php
                        $no = 1;
                    @endphp
                    @foreach ($dataExcelMk as $keyDataExcelMhs => $dataExcelMhs)
                        <tr>
                            <td style="text-align: center;border: 1px solid black">{{ $no++ }}.</td>
                            <td data-format="0" style="text-align: left;border: 1px solid black">
                                {{ @$dataExcelMhs['nim'] }}</td>
                            <td style="border: 1px solid black">{{ @$dataExcelMhs['mhs_nama'] }}</td>
                            <td style="text-align: center;border: 1px solid black">{{ @$dataExcelMhs['mhs_jk_id'] == 8 ? 'L' : 'P' }}</td>
                            <td style="border: 1px solid black">{{ $p->nama }}</td>
                            <td style="border: 1px solid black">{{ @$dataExcelMhs['mk_nama'] }}</td>
                            <td style="border: 1px solid black">{{ @$dataExcelMhs['kelompok_kode'] }}</td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        @endforeach
    @endforeach
@endforeach

@extends('layouts.admin.template')
@section('title')
    {{ auth()->user()->role->level }} | Laporan Pembayaran Mahasiswa
@endsection
@section('css')
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('/admin/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/admin/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/admin/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('/admin/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('/admin/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <!-- Daterange picker bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.4/css/bootstrap-datepicker.css"
        rel="stylesheet" />
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet"
        href="{{ asset('/admin/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="{{ asset('/admin/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">
@endsection
@section('content')
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6 d-flex flex-row">
                        <h1>Laporan Pembayaran Mahasiswa</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item active">/ Laporan Pembayaran Mahasiswa
                            </li>
                        </ol>
                    </div>
                </div>
                {{-- <button type="button" class="btn btn-primary w-100" data-toggle="modal" data-target="#modal-lg">
                    <i class="fas fa-plus-circle mx-2"></i>Tambah Mapel</button> --}}
            </div><!-- /.container-fluid -->
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                {{-- Harian --}}
                <div class="card" id="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Harian
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}" data-source-selector="#card_body"
                                data-load-on-init="false" id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body">
                        <form action="{{ route('admin.mhs.laporan.harian') }}" method="POST" id="form_harian"
                            target="_blank">
                            @csrf
                            <div class="form-group">
                                <label for="tanggal">Tanggal</label>
                                <div class="input-group date">
                                    <input type="input" name="tanggal" class="form-control" id="tanggal"
                                        style="cursor: pointer" placeholder="Masukkan Tanggal" required>
                                    <div class="input-group-append" id="tanggal_btn" style="cursor: pointer">
                                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="kategori">Kategori</label>
                                <select class="form-control select2bs4 w-100" name="kategori" id="kategori">
                                    <option>Semua</option>
                                    <option>Mahasiswa</option>
                                </select>
                            </div>
                            <div id="isi_kategori">

                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success w-100" name="action"
                                            value="excel"><i class="fas fa-download mx-2"></i>Download Excel</button>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success w-100" name="action"
                                            value="excelTotalan"><i class="fas fa-download mx-2"></i>Download
                                            Totalan</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-danger w-100" name="action" value="pdf"><i
                                        class="fas fa-download mx-2"></i>Download PDF</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
                {{-- Bulanan --}}
                <div class="card" id="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Bulanan
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}" data-source-selector="#card_body_bulanan"
                                data-load-on-init="false" id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body_bulanan">
                        <form action="{{ route('admin.mhs.laporan.bulanan') }}" method="POST" id="form_bulanan"
                            target="_blank">
                            @csrf
                            <div class="form-group">
                                <label for="bulan">Bulan</label>
                                <div class="input-group date">
                                    <input type="input" name="bulan" class="form-control" id="bulan"
                                        style="cursor: pointer" placeholder="Masukkan bulan" required>
                                    <div class="input-group-append" id="bulan_btn" style="cursor: pointer">
                                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="kategori_bulanan">Kategori</label>
                                <select class="form-control select2bs4 w-100" name="kategori" id="kategori_bulanan">
                                    <option>Semua</option>
                                    <option>Mahasiswa</option>
                                </select>
                            </div>
                            <div id="isi_kategori_bulanan">

                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-success w-100" name="action" value="excel"><i
                                        class="fas fa-download mx-2"></i>Download Excel</button>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-danger w-100" name="action" value="pdf"><i
                                        class="fas fa-download mx-2"></i>Download PDF</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
                {{-- Tahunan --}}
                <div class="card" id="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Tahunan
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}" data-source-selector="#card_body_tahunan"
                                data-load-on-init="false" id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body_tahunan">
                        <form action="{{ route('admin.mhs.laporan.tahunan') }}" method="POST" id="form_tahunan"
                            target="_blank">
                            @csrf
                            <div class="form-group">
                                <label for="tahun">Tahun</label>
                                <div class="input-group date">
                                    <input type="input" name="tahun" class="form-control" id="tahun"
                                        style="cursor: pointer" placeholder="Masukkan tahun" required>
                                    <div class="input-group-append" id="tahun_btn" style="cursor: pointer">
                                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="kategori_tahunan">Kategori</label>
                                <select class="form-control select2bs4 w-100" name="kategori" id="kategori_tahunan">
                                    <option>Semua</option>
                                    <option>Mahasiswa</option>
                                </select>
                            </div>
                            <div id="isi_kategori_tahunan">

                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-success w-100" name="action" value="excel"><i
                                        class="fas fa-download mx-2"></i>Download Excel</button>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-danger w-100" name="action" value="pdf"><i
                                        class="fas fa-download mx-2"></i>Download PDF</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
                {{-- Rekap --}}
                <div class="card" id="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Rekap
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}" data-source-selector="#card_body_rekap"
                                data-load-on-init="false" id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body_rekap">
                        <form action="{{ route('admin.mhs.laporan.rekap') }}" method="POST">
                            <div class="form-group">
                                @csrf
                                <div class="form-group">
                                    <label for="tahun_rekap">Tahun Rekap</label>
                                    <div class="input-group date">
                                        <input type="input" name="tahun_rekap" class="form-control" id="tahun_rekap"
                                            style="cursor: pointer" placeholder="Masukkan Tahun Rekap"
                                            value="{{ old('tahun_rekap') }}" required>
                                        <div class="input-group-append" id="tahun_rekap_btn" style="cursor: pointer">
                                            <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="bulan_rekap">Bulan</label>
                                    <select class="form-control select2bs4 w-100" name="bulan_rekap" id="bulan_rekap"
                                        required>
                                        @foreach ($bulanRekap as $br)
                                            <option value="{{ $br->data }}">{{ $br->bulan }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <small class="form-text text-muted">**Mohon bersabar nggih,
                                    prosesnya untuk rekap memang lama</small>
                                <button type="submit" class="btn btn-success w-100 my-2" name="action"
                                    value="excel"><i class="fas fa-download mx-2"></i>Download Excel</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

                {{-- Rekap Khusus Tahun --}}
                <div class="card" id="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Rekap Khusus Tahun
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}"
                                data-source-selector="#card_body_rekap_tahun" data-load-on-init="false"
                                id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body_rekap_tahun">
                        <form action="{{ route('admin.mhs.laporan.rekap.tahunan') }}" method="POST">
                            <div class="form-group">
                                @csrf
                                <div class="form-group">
                                    <label for="tahun_rekap_tahunan">Tahun Rekap</label>
                                    <div class="input-group date">
                                        <input type="input" name="tahun_rekap" class="form-control"
                                            id="tahun_rekap_tahunan" style="cursor: pointer"
                                            placeholder="Masukkan Tahun Rekap" required>
                                        <div class="input-group-append" id="tahun_rekap_tahunan_btn"
                                            style="cursor: pointer">
                                            <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 my-2" name="action"
                                    value="excel"><i class="fas fa-download mx-2"></i>Download Excel</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

                {{-- Jumlah Yang Sudah Membayar dan Belum --}}
                <div class="card">
                    <div class="card-header">
                        <a class="text-bold" style="color:red;">
                            <i class="fas fa-info mx-2"></i>Jumlah Mahasiswa yang Belum dan Sudah Membayar
                        </a>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="card-refresh"
                                data-source="{{ route('admin.mhs.laporan') }}" data-source-selector="#card_body_jumlah"
                                data-load-on-init="false" id="card_refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="maximize">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card_body_jumlah">
                        <form action="{{ route('admin.mhs.laporan.jumlah-mahasiswa-bayar') }}" method="POST"
                            target="_blank">
                            <div class="form-group">
                                @csrf
                                <div class="form-group">
                                    <label for="jumlah_tahun_angkatan">Tahun Akademik</label>
                                    <select class="form-control select2bs4 w-100" name="tahun_akademik">
                                        <option value="semua">Semua</option>
                                        @foreach ($tahunAkademik as $ta)
                                            <option value="{{ $ta->id }}">{{ $ta->nama }} {{ $ta->semester }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="jumlah_prodi">Prodi</label>
                                    <select class="form-control select2bs4 w-100" name="prodi">
                                        <option value="semua">Semua</option>
                                        @foreach ($prodi as $p)
                                            <option value="{{ $p->id }}">{{ $p->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="jumlah_jenis_kelamin">Jenis Kelamin</label>
                                    <select class="form-control select2bs4 w-100" name="jenis_kelamin"
                                        id="jumlah_jenis_kelamin">
                                        <option value="semua">Semua</option>
                                        <option value="8">Putra</option>
                                        <option value="9">Putri</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100 my-2" name="action"
                                    value="excel"><i class="fas fa-download mx-2"></i>Download Excel</button>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('script')
    <!-- DataTables  & Plugins -->
    <script src="{{ asset('/admin/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <!-- jquery-validation -->
    <script src="{{ asset('/admin/plugins/jquery-validation/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('/admin/plugins/jquery-validation/additional-methods.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('/admin/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <!-- InputMask -->
    <script src="{{ asset('/admin/plugins/inputmask/jquery.inputmask.min.js') }}"></script>
    <!-- SweetAlert2 -->
    <script src="{{ asset('/admin/plugins/sweetalert2/sweetalert2.min.js') }}"></script>
    <!-- Datepicker bootstrap -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.4/js/bootstrap-datepicker.js"></script>
    <!-- Select2 -->
    <script src="{{ asset('/admin/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            const jk = @json(Helper::getJenisKelaminUser());

            //Initialize Select2 Elements
            $('.select2bs4').select2({
                theme: 'bootstrap4'
            })

            //Date picker for form add
            $('#tanggal').datepicker({
                format: 'yyyy-mm-dd',
                todayBtn: "linked",
                todayHighlight: true,
                autoclose: true
            });

            $('#tanggal_btn').click(function(e) {
                $('#tanggal').datepicker('show');
            });

            setDate('#tanggal');

            $('#bulan').datepicker({
                format: "mm-yyyy",
                viewMode: "months",
                minViewMode: "months",
                todayBtn: "linked",
                todayHighlight: true,
                autoclose: true
            });

            $('#bulan_btn').click(function(e) {
                $('#bulan').datepicker('show');
            });

            setDate('#bulan');

            $('#tahun').datepicker({
                format: "yyyy",
                viewMode: "years",
                minViewMode: "years",
                todayBtn: "linked",
                todayHighlight: true,
                autoclose: true
            });

            $('#tahun_btn').click(function(e) {
                $('#tahun').datepicker('show');
            });

            setDate('#tahun');

            $('#tahun_rekap').datepicker({
                minViewMode: 2,
                format: 'yyyy'
            });

            $('#tahun_rekap').change(function() {
                $('#tahun_rekap').datepicker('hide');
            });

            $('#tahun_rekap_btn').click(function(e) {
                $('#tahun_rekap').datepicker('show');
            });

            setDate('#tahun_rekap');

            $('#tahun_rekap_tahunan').datepicker({
                minViewMode: 2,
                format: 'yyyy'
            });

            $('#tahun_rekap_tahunan').change(function() {
                $('#tahun_rekap_tahunan').datepicker('hide');
            });

            $('#tahun_rekap_tahunan_btn').click(function(e) {
                $('#tahun_rekap_tahunan').datepicker('show');
            });

            setDate('#tahun_rekap_tahunan');

            $('#kategori').change(function(e) {
                const kategori = (this.value).toLowerCase();
                $('#isi_kategori').empty();
                switch (kategori) {
                    case "mahasiswa":
                        kategoriMahasiswa('#isi_kategori', 'harian');
                        break;
                    case "calon mahasiswa":
                        kategoriCalonMahasiswa('#isi_kategori', 'harian');
                    default:
                        break;
                }
            });

            $('#kategori_bulanan').change(function(e) {
                const kategori = (this.value).toLowerCase();
                $('#isi_kategori_bulanan').empty();
                switch (kategori) {
                    case "mahasiswa":
                        kategoriMahasiswa('#isi_kategori_bulanan', 'bulanan');
                        break;
                    case "calon mahasiswa":
                        kategoriCalonMahasiswa('#isi_kategori_bulanan', 'bulanan');
                    default:
                        break;
                }
            });

            $('#kategori_tahunan').change(function(e) {
                const kategori = (this.value).toLowerCase();
                $('#isi_kategori_tahunan').empty();
                switch (kategori) {
                    case "mahasiswa":
                        kategoriMahasiswa('#isi_kategori_tahunan', 'tahunan');
                        break;
                    case "calon mahasiswa":
                        kategoriCalonMahasiswa('#isi_kategori_tahunan', 'tahunan');
                    default:
                        break;
                }
            });

            if (jk.id == "%") jk.id = 'semua';
            $('#jumlah_jenis_kelamin').val(jk.id).change();
        });
    </script>

    <script>
        function deleteData(event) {
            event.preventDefault();
            var id = event.target.querySelector('input[name="id"]').value;
            Swal.fire({
                title: 'Yakin menghapus data ?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Iya',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ route('admin.master.mhs.jenis-tagihan.delete') }}";
                    var fd = new FormData($(event.target)[0]);

                    $.ajax({
                        type: "post",
                        url: url,
                        data: fd,
                        contentType: false,
                        processData: false,
                        success: function(response) {
                            swalToast(response.message, response.data);
                            cardRefresh();
                        }
                    });
                }
            })
            // swalDelete(id, username, event.target);
        }

        function cardRefresh() {
            var cardRefresh = document.querySelector('#card_refresh');
            cardRefresh.click();
        }

        function swalToast(message, data) {
            var Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            if (message == 200) {
                Toast.fire({
                    icon: 'success',
                    title: data
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data
                });
            }
        }

        function setDate(eId) {
            switch (eId) {
                case "#tanggal":
                    Date.prototype.toDateInputValue = (function() {
                        var local = new Date(this);
                        local.setMinutes(this.getMinutes() - this.getTimezoneOffset());
                        return local.toJSON().slice(0, 10);
                    });
                    $(eId).val(new Date().toDateInputValue());
                    break;
                case "#bulan":
                    var date = new Date();
                    var month = date.getMonth() + 1;
                    var year = date.getFullYear();
                    $(eId).val(`${month}-${year}`);
                    break;
                case "#tahun":
                    var date = new Date();
                    var year = date.getFullYear();
                    $(eId).val(`${year}`);
                    break;
                case "#tahun_rekap":
                    var date = new Date();
                    var year = date.getFullYear();
                    $(eId).val(`${year}`);
                case "#tahun_rekap_tahunan":
                    var date = new Date();
                    var year = date.getFullYear();
                    $(eId).val(`${year}`);
                default:
                    break;
            }
        }

        function kategoriMahasiswa(eid) {
            var content = `
                <div class="form-group">
                    <label for="prodi">Prodi</label>
                    <select class="form-control select2bs4 w-100" name="prodi">
                        <option>Semua</option>`

            @foreach ($prodi as $p)
                content += `<option value="{{ $p->id }}">{{ $p->nama }}</option>`
            @endforeach
            content += `</select></div>`;
            content += `
                <div class="form-group">
                    <label for="tahun_akademik">Tahun Akademik</label>
                    <select class="form-control select2bs4 w-100" name="tahun_akademik">
                        <option>Semua</option>`
            @foreach ($tahunAkademik as $ta)
                content += `<option  value="{{ $ta->id }}">{{ $ta->nama }} - {{ $ta->semester }}</option>`
            @endforeach
            content += `</select></div>`;
            content += `
                <div class="form-group">
                    <label for="jenis_pembayaran">Jenis Pembayaran</label>
                    <select class="form-control select2bs4 w-100" name="jenis_pembayaran">
                        <option>Semua</option>`
            @foreach ($KeuanganJenisPembayaran as $jenisPembayaran)
                content += `<option value="{{ $jenisPembayaran->id }}">{{ $jenisPembayaran->nama }}</option>`
            @endforeach
            content += `</select>
                </div>
            `;
            $(eid).append(content);

            //Initialize Select2 Elements
            $('.select2bs4').select2({
                theme: 'bootstrap4'
            })
        }

        function kategoriCalonMahasiswa(eid, jenis) {
            var content = `
            <div class="form-group">
                    <label for="jenis_pembayaran">Jenis Pembayaran</label>
                    <select class="form-control select2bs4 w-100" name="jenis_pembayaran">
                        <option>Semua</option>`
            @foreach (BulkData::jenisPembayaranCalonMahasiswa as $jenisPembayaranCalonMahasiswa)
                content += `<option>{{ $jenisPembayaranCalonMahasiswa }}</option>`
            @endforeach
            content += `</select>
                </div>`;
            $('#isi_kategori').append(content);

            //Initialize Select2 Elements
            $('.select2bs4').select2({
                theme: 'bootstrap4'
            })
        }
    </script>
@endsection

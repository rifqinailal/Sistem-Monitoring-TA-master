@extends('administrator.layout.main')
@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-block-helper me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="alert alert-warning">
                <i class="bx bx-info-circle me-2"></i> Silakan tentukan rentang tanggal untuk generate jadwal otomatis. Pilih mahasiswa dari tabel di bawah ini yang ingin dijadwalkan, lalu klik tombol "Mulai Generate".
            </div>

            <form action="{{ route('apps.jadwal-sidang.generate-auto') }}" method="POST" id="formGenerate">
                @csrf

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label fw-bold">Tanggal Mulai Penjadwalan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="start_date" id="start_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label fw-bold">Tanggal Akhir Penjadwalan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="end_date" id="end_date" required>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title m-0">Daftar Mahasiswa Menunggu Penjadwalan Sidang</h5>
                    <div>
                        <a href="{{ route('apps.jadwal-sidang') }}" class="btn btn-secondary"><i class="bx bx-x"></i> Batal</a>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">
                            <i class="bx bx-cog"></i> Mulai Generate
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="datatable">
                        <thead class="table-light">
                            <tr>
                                <th width="5%" class="text-center">
                                    <input type="checkbox" id="selectAll" class="form-check-input" style="transform: scale(1.3);">
                                </th>
                                <th width="25%">Mahasiswa</th>
                                <th width="35%">Judul</th>
                                <th width="35%">Dosen Terkait</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $item)
                                <tr>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" name="selected_ids[]" value="{{ $item->id }}" class="form-check-input item-checkbox" style="transform: scale(1.3);">
                                    </td>
                                    <td>
                                        <p class="fw-bold m-0">{{ $item->tugas_akhir->mahasiswa->nama_mhs }}</p>
                                        <p class="small text-muted m-0">NIM : {{ $item->tugas_akhir->mahasiswa->nim }}</p>
                                        <span class="badge badge-soft-primary">{{ $item->tugas_akhir->mahasiswa->programStudi->display ?? '' }}</span>
                                    </td>
                                    <td>
                                        <h6 class="font-size-13 m-0">{{ $item->tugas_akhir->judul }}</h6>
                                        <span class="badge small badge-soft-secondary mt-1">{{ $item->tugas_akhir->tipe == 'I' ? 'Individu' : 'Kelompok' }}</span>
                                    </td>
                                    <td>
                                        <p class="fw-bold small m-0 text-primary">Pembimbing:</p>
                                        <ul class="ps-3 mb-1 small">
                                            @foreach ($item->tugas_akhir->bimbing_uji->where('jenis', 'pembimbing')->sortBy('urut') as $dosen)
                                                <li>{{ $dosen->dosen->name ?? '-' }} (PB {{ $dosen->urut }})</li>
                                            @endforeach
                                        </ul>
                                        <p class="fw-bold small m-0 text-success">Penguji:</p>
                                        <ul class="ps-3 mb-0 small">
                                            @foreach ($item->tugas_akhir->bimbing_uji->whereIn('jenis', ['penguji', 'pengganti'])->sortBy('urut') as $dosen)
                                                <li>{{ $dosen->dosen->name ?? '-' }} ({{ strtoupper(substr($dosen->jenis, 0, 2)) }} {{ $dosen->urut }})</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4">Tidak ada mahasiswa yang menunggu penjadwalan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="mt-3 text-primary fw-bold">Algoritma Sedang Bekerja...</h5>
        <p class="text-muted">Mencari kombinasi jadwal terbaik tanpa bentrok. Mohon tunggu.</p>
    </div>
@endsection

@section('js')
<script>
    $(document).ready(function() {
        var table = $('#datatable').DataTable();

        $('#selectAll').on('click', function() {
            var rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"].item-checkbox', rows).prop('checked', this.checked);
        });

        $('#formGenerate').on('submit', function(e) {
            var form = this;
            var checkedNodes = table.$('.item-checkbox:checked');

            if (checkedNodes.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih minimal satu mahasiswa untuk dijadwalkan!'
                });
                return false;
            }

            checkedNodes.each(function(){
               if(!$.contains(document, this)){
                  $(form).append(
                     $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', this.name)
                        .val(this.value)
                  );
               }
            });

            $('#loadingOverlay').css('display', 'flex');
            $('#btnSubmit').prop('disabled', true);
        });
    });
</script>
@endsection

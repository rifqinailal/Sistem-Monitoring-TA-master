@extends('administrator.layout.main')
@section('content')
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-12">
            <div class="card">
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-all me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                            </button>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-block-helper me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                            </button>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-error alert-danger alert-dismissible fade show" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                            </button>
                        </div>
                    @endif
                    <form action="{{ route('apps.jadwal-seminar.update', ['jadwalSeminar' => $jadwalSeminar->id]) }}"
                        method="post">
                        @csrf
                        <div class="mb-3">
                            <label for="">Nama Mahasiswa<span class="text-danger"> *</span></label>
                            <input type="text" name="name" class="form-control"
                                value="{{ $jadwalSeminar->tugas_akhir->mahasiswa->nim }} - {{ $jadwalSeminar->tugas_akhir->mahasiswa->nama_mhs }}"
                                readonly>
                        </div>
                        <div class="mb-3">
                            <label for="">Ruangan<span class="text-danger"> *</span></label>
                            <select name="ruangan" class="form-control">
                                <option value="">Pilih Ruangan</option>
                                @foreach ($ruangan as $item)
                                    <option value="{{ $item->id }}"
                                        {{ (isset($editedData) && $editedData->ruangan_id == $item->id) || old('ruangan') == $item->id ? 'selected' : '' }}>
                                        {{ $item->kode }}-{{ $item->nama_ruangan }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="">Tanggal<span class="text-danger"> *</span></label>
                            <input type="date" name="tanggal" class="form-control"
                                value="{{ old('tanggal', isset($editedData) ? $editedData->tanggal : '') }}">
                        </div>
                        <div class="mb-3">
                            <label for="">Jam Mulai<span class="text-danger"> *</span></label>
                            <input type="time" name="jam_mulai" class="form-control"
                                value="{{ old('jam_mulai', isset($editedData) ? $editedData->jam_mulai : '') }}">
                        </div>
                        <div class="mb-3">
                            <label for="">Jam Selesai<span class="text-danger"> *</span></label>
                            <input type="time" name="jam_selesai" class="form-control"
                                value="{{ old('jam_selesai', isset($editedData) ? $editedData->jam_selesai : '') }}">
                        </div>
                        <a href="{{ route('apps.jadwal-seminar') }}" class="btn btn-secondary">Kembali</a>
                        <button class="btn btn-primary" type="submit">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h4>Jadwal Dosen Terdaftar</h4>
                    <hr class="mb-3">
                    <div class="accordion" id="accordion">
                        @foreach ($jadwalSeminar->tugas_akhir->bimbing_uji as $item)
                            @if ($item->jenis == 'pembimbing' && $item->urut == 1)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapsePembimbing{{ $item->urut }}" aria-expanded="true"
                                            aria-controls="collapsePembimbing{{ $item->urut }}">
                                            {{ $item->dosen->name }}
                                        </button>
                                    </h2>
                                    <div id="collapsePembimbing{{ $item->urut }}"
                                        class="accordion-collapse collapse show" data-bs-parent="#accordion">
                                        <div class="accordion-body">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th>Jam Mulai</th>
                                                        <th>Jam Sesai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if ($jadwalPembimbing1->count() > 0)
                                                        @foreach ($jadwalPembimbing1 as $p1)
                                                            <tr>
                                                                <td>{{ $p1->tanggal }}</td>
                                                                <td>{{ $p1->jam_mulai }}</td>
                                                                <td>{{ $p1->jam_selesai }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr class="text-center">
                                                            <td colspan="3">Tidak ada data</td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if ($item->jenis == 'pembimbing' && $item->urut == 2)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapsePembimbing{{ $item->urut }}" aria-expanded="false"
                                            aria-controls="collapsePembimbing{{ $item->urut }}">
                                            {{ $item->dosen->name }}
                                        </button>
                                    </h2>
                                    <div id="collapsePembimbing{{ $item->urut }}" class="accordion-collapse collapse"
                                        data-bs-parent="#accordion">
                                        <div class="accordion-body">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th>Jam Mulai</th>
                                                        <th>Jam Sesai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if ($jadwalPembimbing2->count() > 0)
                                                        @foreach ($jadwalPembimbing2 as $p2)
                                                            <tr>
                                                                <td>{{ $p2->tanggal }}</td>
                                                                <td>{{ $p2->jam_mulai }}</td>
                                                                <td>{{ $p2->jam_selesai }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr class="text-center">
                                                            <td colspan="3">Tidak ada data</td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if ($item->jenis == 'penguji' && $item->urut == 1)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapsePenguji1{{ $item->urut }}" aria-expanded="false"
                                            aria-controls="collapsePenguji1{{ $item->urut }}">
                                            {{ $item->dosen->name }}
                                        </button>
                                    </h2>
                                    <div id="collapsePenguji1{{ $item->urut }}" class="accordion-collapse collapse"
                                        data-bs-parent="#accordion">
                                        <div class="accordion-body">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th>Jam Mulai</th>
                                                        <th>Jam Sesai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if ($jadwalPenguji1->count() > 0)
                                                        @foreach ($jadwalPenguji1 as $p1)
                                                            <tr>
                                                                <td>{{ $p1->tanggal }}</td>
                                                                <td>{{ $p1->jam_mulai }}</td>
                                                                <td>{{ $p1->jam_selesai }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr class="text-center">
                                                            <td colspan="3">Tidak ada data</td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if ($item->jenis == 'penguji' && $item->urut == 2)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#collapsePenguji1{{ $item->urut }}" aria-expanded="false"
                                            aria-controls="collapsePenguji1{{ $item->urut }}">
                                            {{ $item->dosen->name }}
                                        </button>
                                    </h2>
                                    <div id="collapsePenguji1{{ $item->urut }}" class="accordion-collapse collapse"
                                        data-bs-parent="#accordion">
                                        <div class="accordion-body">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Tanggal</th>
                                                        <th>Jam Mulai</th>
                                                        <th>Jam Sesai</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if ($jadwalPenguji2->count() > 0)
                                                        @foreach ($jadwalPenguji2 as $p2)
                                                            <tr>
                                                                <td>{{ $p2->tanggal }}</td>
                                                                <td>{{ $p2->jam_mulai }}</td>
                                                                <td>{{ $p2->jam_selesai }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr class="text-center">
                                                            <td colspan="3">Tidak ada data</td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-sm-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="m-0">Jadwal Mahasiswa Terdaftar</h4>
                    <span class="small text-muted">Jadwal untuk satu minggu kedepan</span>
                    <hr class="mb-0 mt-1">
                    <table class="table table-responsive">
                        <thead style="color: steelblue">
                            <th>Nama Mahasiswa</th>
                            <th>Jenis</th>
                            <th>Ruangan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                        </thead>
                        <tbody>
                            @if ($mahasiswaTerdaftar->count() > 0)
                                @foreach ($mahasiswaTerdaftar as $item)
                                    <tr>
                                        <td>{{ $item->tugas_akhir->mahasiswa->nama_mhs }}</td>
                                        <td>{{ $item->tugas_akhir->tipe == 'K' ? 'Kelompok' : 'Individu' }}</td>
                                        <td>{{ $item->ruangan->nama_ruangan }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal)->locale('id')->translatedFormat('l, d F Y') }}
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($item->jam_mulai)->locale('id')->translatedFormat('H:i') }}
                                            -
                                            {{ \Carbon\Carbon::parse($item->jam_selesai)->locale('id')->translatedFormat('H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr class="text-center">
                                    <td colspan="5">
                                        <div class="col-md-4 col-sm-7 col-10 mx-auto">
                                            <img src="{{ asset('assets/images/no-data.png') }}" alt=""
                                                width="100%">
                                        </div>
                                        <p>Belum ada jadwal dalam satu minggu kedepan</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <h4 class="m-0">Rekomendasi Slot Tersedia</h4>
                    <span class="small text-muted">Pilih slot untuk mengisi form secara otomatis</span>
                    <hr class="mb-3 mt-1">

                    <ul class="nav nav-pills mb-3 nav-justified" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pills-future-tab" data-bs-toggle="pill" data-bs-target="#pills-future" type="button" role="tab" aria-controls="pills-future" aria-selected="true">
                                <i class="fas fa-arrow-right"></i> Setelahnya
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pills-past-tab" data-bs-toggle="pill" data-bs-target="#pills-past" type="button" role="tab" aria-controls="pills-past" aria-selected="false">
                                <i class="fas fa-arrow-left"></i> Sebelumnya
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">

                        <div class="tab-pane fade show active" id="pills-future" role="tabpanel" aria-labelledby="pills-future-tab">
                            <div class="list-group">
                                @php $futureSlots = collect($rekomendasiSlots)->where('status_waktu', 'Akan Datang'); @endphp
                                @forelse($futureSlots as $slot)
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action slot-rekomendasi"
                                        data-tgl="{{ $slot['tanggal'] }}" data-mulai="{{ $slot['jam_mulai'] }}"
                                        data-selesai="{{ $slot['jam_selesai'] }}" data-ruangan="{{ $slot['ruangan_id'] }}" style="border-left: 4px solid #198754;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $slot['tanggal_indo'] }}</strong><br>
                                                <span class="badge bg-light text-dark border"><i class="far fa-clock"></i> {{ $slot['jam_mulai'] }} - {{ $slot['jam_selesai'] }}</span>
                                                <span class="badge bg-light text-dark border"><i class="fas fa-door-open"></i> {{ $slot['nama_ruangan'] }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="alert alert-light text-center border mb-0">Tidak ada slot kosong kedepan.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pills-past" role="tabpanel" aria-labelledby="pills-past-tab">
                            <div class="list-group">
                                @php $pastSlots = collect($rekomendasiSlots)->where('status_waktu', 'Masa Lalu'); @endphp
                                @forelse($pastSlots as $slot)
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action slot-rekomendasi"
                                        data-tgl="{{ $slot['tanggal'] }}" data-mulai="{{ $slot['jam_mulai'] }}"
                                        data-selesai="{{ $slot['jam_selesai'] }}" data-ruangan="{{ $slot['ruangan_id'] }}" style="border-left: 4px solid #6c757d;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $slot['tanggal_indo'] }}</strong><br>
                                                <span class="badge bg-light text-dark border"><i class="far fa-clock"></i> {{ $slot['jam_mulai'] }} - {{ $slot['jam_selesai'] }}</span>
                                                <span class="badge bg-light text-dark border"><i class="fas fa-door-open"></i> {{ $slot['nama_ruangan'] }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="alert alert-light text-center border mb-0">Tidak ada slot kosong sebelumnya.</div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>
        </div>
@endsection

@section('js')
    <script>
        $(document).ready(function() {
            $(document).on('click', '.slot-rekomendasi', function(e) {
                e.preventDefault();
                let tgl = $(this).data('tgl');
                let mulai = $(this).data('mulai');
                let selesai = $(this).data('selesai');
                let ruangan = $(this).data('ruangan');

                $('input[name="tanggal"]').val(tgl);
                $('input[name="jam_mulai"]').val(mulai);
                $('input[name="jam_selesai"]').val(selesai);

                let selectRuangan = $('select[name="ruangan"]');
                selectRuangan.val(ruangan).trigger('change');

                $('html, body').animate({
                    scrollTop: $("form").offset().top - 50
                }, 500);

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Jadwal berhasil disalin ke form!',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            });
        });
    </script>
@endsection

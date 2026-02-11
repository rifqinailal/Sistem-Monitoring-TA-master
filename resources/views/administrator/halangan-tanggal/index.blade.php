@extends('administrator.layout.main')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        @can('create-halangan-tanggal')
                       
                        <button onclick="tambahData('{{ route('apps.halangan-tanggal.store') }}')" class="btn btn-primary waves-effect waves-light">
                            <i class="bx bx-plus"></i> Tambah Ijin / Halangan
                        </button>
                        @endcan
                    </div>
                </div>

                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-all me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif
                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-block-helper me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">No</th>
                                <th>Tanggal</th>
                                <th>Dosen</th>
                                <th>Waktu / Sesi</th>
                                <th>Keterangan</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dataHalangan as $i => $item)
                            <tr>
                                <td>{{ ++$i }}</td>
                                <td>

                                    <span class="badge bg-info font-size-12">
                                        {{ \Carbon\Carbon::parse($item['tanggal'])->isoFormat('dddd, D MMMM Y') }}
                                    </span>
                                </td>
                                <td><span class="fw-bold">{{ $item['dosen_nama'] }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        {{ \Carbon\Carbon::parse($item['jam_mulai'])->format('H:i') }} -
                                        {{ \Carbon\Carbon::parse($item['jam_selesai'])->format('H:i') }}
                                    </div>
                                    <small class="text-muted">
                                        (Sesi: {{ implode(', ', $item['sesi_list']) }})
                                    </small>
                                </td>
                                <td>{{ $item['keterangan'] }}</td>
                                <td>
                                    @can('update-halangan-tanggal')

                                    <button onclick="editData('{{ $item['id'] }}', '{{ route('apps.halangan-tanggal.show', $item['id']) }}', '{{ route('apps.halangan-tanggal.update') }}')"
                                        class="btn btn-outline-warning btn-sm" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                    @endcan
                                    @can('delete-halangan-tanggal')
                                    <button onclick="hapusHalangan('{{ $item['id'] }}', '{{ route('apps.halangan-tanggal.delete', $item['id']) }}')"
                                        class="btn btn-outline-danger btn-sm" title="Hapus">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('administrator.halangan-tanggal.partials.modal')
@endsection

@extends('administrator.layout.main')
@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            @can('create-sesi-ujian')
                                <button onclick="tambahData()" class="btn btn-primary waves-effect waves-light">
                                    <i class="bx bx-plus"></i> Tambah
                                </button>
                                <hr>
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
                            <i class="mdi mdi-block-helper me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table id="datatable" class="table table-striped table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama Sesi</th>
                                    <th>Waktu Mulai</th>
                                    <th>Waktu Selesai</th>
                                    <th>Status</th>
                                    <th width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dataSesiUjian as $i => $item)
                                    <tr>
                                        <td>{{ ++$i }}</td>
                                        <td>{{ $item->nama }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->jam_mulai)->format('H:i') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->jam_selesai)->format('H:i') }}</td>
                                        <td>
                                            @if ($item->is_active)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-danger">Tidak Aktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('update-sesi-ujian')

                                                <button onclick="editData('{{ $item->id }}', '{{ route('apps.sesi-ujian.show', $item->id) }}')"
                                                    title="Edit" class="btn btn-outline-primary btn-sm mx-1 my-1">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                            @endcan
                                            @can('delete-sesi-ujian')

                                                <button onclick="hapusSesi('{{ $item->id }}', '{{ route('apps.sesi-ujian.delete', $item->id) }}')"
                                                    title="Hapus" class="btn btn-outline-dark btn-sm mx-1 my-1">
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

    @include('administrator.sesi-ujian.partials.modal')
@endsection



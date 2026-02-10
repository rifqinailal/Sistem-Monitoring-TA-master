@extends('administrator.layout.main')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            @can('create-halangan-rutin')
                                <button onclick="tambahData('{{ route('apps.halangan-rutin.store') }}')"
                                    class="btn btn-primary waves-effect waves-light">
                                    <i class="bx bx-plus"></i> Tambah Jadwal Rutin
                                </button>
                            @endcan
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-all me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-block-helper me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table id="datatable" class="table table-bordered table-striped dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Dosen</th>
                                    <th>Hari</th>
                                    <th>Waktu / Sesi</th>
                                    <th>Ruangan / Ket</th>
                                    <th width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dataHalangan as $i => $item)
                                    <tr>
                                        <td>{{ ++$i }}</td>
                                        <td><span class="fw-bold">{{ $item['dosen_nama'] }}</span></td>
                                        <td>
                                            @php
                                                $badges = [
                                                    'Senin' => 'bg-primary',
                                                    'Selasa' => 'bg-info',
                                                    'Rabu' => 'bg-success',
                                                    'Kamis' => 'bg-warning',
                                                    'Jumat' => 'bg-danger',
                                                ];
                                            @endphp
                                            <span class="badge {{ $badges[$item['hari']] ?? 'bg-secondary' }} font-size-12">
                                                {{ $item['hari'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">
                                                {{ \Carbon\Carbon::parse($item['jam_mulai'])->format('H:i') }} -
                                                {{ \Carbon\Carbon::parse($item['jam_selesai'])->format('H:i') }}
                                            </div>
                                            <small class="text-muted">
                                                (Sesi: {{ implode(', ', $item['sesi_list']) }})
                                            </small>
                                        </td>
                                        <td>
                                            @if ($item['ruangan_nama'] == 'Lainnya')
                                                <i class="mdi mdi-map-marker-off text-muted"></i> <em>Lainnya</em> <br>
                                                <small class="text-danger">{{ $item['keterangan'] }}</small>
                                            @else
                                                <i class="mdi mdi-map-marker text-primary"></i> {{ $item['ruangan_nama'] }}
                                                <br>
                                                <small class="text-muted">{{ $item['keterangan'] }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @can('update-halangan-rutin')
                                                <button
                                                    onclick="editData('{{ $item['id'] }}', '{{ route('apps.halangan-rutin.show', $item['id']) }}', '{{ route('apps.halangan-rutin.update') }}')"
                                                    class="btn btn-outline-primary btn-sm" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </button>
                                            @endcan
                                            @can('delete-halangan-rutin')
                                                <button
                                                    onclick="hapusHalangan('{{ $item['id'] }}', '{{ route('apps.halangan-rutin.delete', $item['id']) }}')"
                                                    class="btn btn-outline-dark btn-sm" title="Hapus">
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

    @include('administrator.halangan-rutin.partials.modal')
@endsection

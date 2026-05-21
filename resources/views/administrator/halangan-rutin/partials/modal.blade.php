<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Form Jadwal Rutin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formHalangan" action="{{ route('apps.halangan-rutin.store') }}" method="POST">
                @csrf

                <input type="hidden" name="original_ids" id="original_ids">

                <div class="modal-body">

                    @if (Auth::user()->hasRole(['Admin', 'Developer']))
                        <div class="mb-3">
                            <label class="form-label">Dosen</label>
                            <select name="dosen_id" id="dosen_id" class="form-control select2" style="width: 100%"
                                required>
                                <option value="">-- Pilih Dosen --</option>
                                @foreach ($dataDosen as $dosen)
                                    <option value="{{ $dosen->id }}">{{ $dosen->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="dosen_id" value="{{ $dosenIdLoggedIn }}">

                    @endif

                    <div class="mb-3">
                        <label class="form-label">Hari</label>
                        <select name="hari" id="hari" class="form-select" required>
                            <option value="">-- Pilih Hari --</option>
                            <option value="Senin">Senin</option>
                            <option value="Selasa">Selasa</option>
                            <option value="Rabu">Rabu</option>
                            <option value="Kamis">Kamis</option>
                            <option value="Jumat">Jumat</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label class="form-label d-block">Pilih Sesi (Bisa lebih dari satu)</label>
                        <div class="row g-2">
                            @foreach ($dataSesi as $sesi)
                                <div class="col-6 col-md-3">
                                    <input type="checkbox" class="btn-check sesi-checkbox" id="sesi_{{ $sesi->id }}"
                                        name="sesi_ujian_ids[]" value="{{ $sesi->id }}" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100 btn-sm" for="sesi_{{ $sesi->id }}">
                                        <strong>{{ $sesi->nama }}</strong> <br>
                                        <small>{{ \Carbon\Carbon::parse($sesi->jam_mulai)->format('H:i') }}</small>
                                        <small>-</small>
                                        <small>{{ \Carbon\Carbon::parse($sesi->jam_selesai)->format('H:i') }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">* Klik untuk memilih sesi jam mengajar.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ruangan</label>
                                <select name="ruangan_id" id="ruangan_id" class="form-control select2"
                                    style="width: 100%">
                                    <option value="">-- Lainnya / Luar Kampus --</option>
                                    @foreach ($dataRuangan as $ruangan)
                                        <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Keterangan / Mata Kuliah</label>
                                <input type="text" class="form-control" id="keterangan" name="keterangan"
                                    placeholder="Contoh: Mengajar Pemrograman Web" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

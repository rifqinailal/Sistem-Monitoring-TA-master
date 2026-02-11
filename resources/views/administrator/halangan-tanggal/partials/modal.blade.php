<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Form Ijin / Halangan Tanggal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formHalangan" method="POST">
                @csrf
                <input type="hidden" name="original_ids" id="original_ids">

                <div class="modal-body">
                    @if(Auth::user()->hasRole(['Admin', 'Kaprodi', 'Developer']))
                    <div class="mb-3">
                        <label class="form-label">Dosen</label>
                        <select name="dosen_id" id="dosen_id" class="form-control select2" style="width: 100%" required>
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
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Pilih Sesi yang Berhalangan</label>
                        <div class="row g-2">
                            @foreach ($dataSesi as $sesi)
                            <div class="col-6 col-md-3">
                                <input type="checkbox" class="btn-check sesi-checkbox" id="sesi_{{ $sesi->id }}"
                                    name="sesi_ujian_ids[]" value="{{ $sesi->id }}" autocomplete="off">
                                <label class="btn btn-outline-danger w-100 btn-sm" for="sesi_{{ $sesi->id }}">
                                    <strong>{{ $sesi->nama }}</strong> <br>
                                    <small>{{ \Carbon\Carbon::parse($sesi->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($sesi->jam_selesai)->format('H:i') }}</small>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan / Alasan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2"
                            placeholder="Contoh: Dinas Luar Kota / Ijin Sakit" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

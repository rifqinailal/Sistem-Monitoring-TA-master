<?php

namespace App\Http\Controllers\Administrator\HalanganRutin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DosenHalanganRutin;
use App\Models\Dosen;
use App\Models\Ruangan;
use App\Models\SesiUjian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HalanganRutinController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = DosenHalanganRutin::with(['dosen', 'sesi', 'ruangan'])
            ->orderBy('dosen_id')
            ->orderBy('hari')
            ->orderBy('sesi_ujian_id');

        $dosenIdLoggedIn = null;

        if ($user->hasRole('Dosen')) {
            $dosenIdLoggedIn = $user->userable_id;
            $query->where('dosen_id', $dosenIdLoggedIn);
        }

        $rawData = $query->get();

        $groupedData = [];
        foreach ($rawData as $row) {
            $key = $row->dosen_id . '-' . $row->hari . '-' . ($row->ruangan_id ?? 'null') . '-' . md5(trim(strtolower($row->keterangan)));

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'id' => $row->id,
                    'dosen_nama' => $row->dosen->name ?? '-',
                    'hari' => $row->hari,
                    'ruangan_nama' => $row->ruangan->nama_ruangan ?? 'Lainnya',
                    'keterangan' => $row->keterangan,
                    'sesi_list' => [],
                    'jam_mulai' => $row->sesi->jam_mulai,
                    'jam_selesai' => $row->sesi->jam_selesai,
                ];
            }

            $groupedData[$key]['sesi_list'][] = $row->sesi->nama;
            $groupedData[$key]['jam_selesai'] = $row->sesi->jam_selesai;
        }

        $data = [
            "title" => "Halangan Rutin Dosen",
            'mods' => 'halangan_rutin',
            'breadcrumb' => [
                ['title' => "Dashboard", 'url' => route('apps.dashboard')],
                ['title' => "Master Data", 'is_active' => true],
                ['title' => "Halangan Rutin", 'is_active' => true]
            ],
            'dosenIdLoggedIn' => $dosenIdLoggedIn,
            'dataDosen' => Dosen::orderBy('name')->get(),
            'dataRuangan' => Ruangan::all(),
            'dataSesi' => SesiUjian::where('is_active', 1)->get(),
            'dataHalangan' => collect($groupedData)->values(),
        ];

        return view('administrator.halangan-rutin.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dosen_id' => 'required',
            'hari' => 'required',
            'sesi_ujian_ids' => 'required|array',
            'sesi_ujian_ids.*' => 'exists:sesi_ujians,id',
            'ruangan_id' => 'nullable|exists:ruangans,id',
            'keterangan' => 'required',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->sesi_ujian_ids as $sesiId) {
                DosenHalanganRutin::firstOrCreate(
                    [
                        'dosen_id' => $request->dosen_id,
                        'hari' => $request->hari,
                        'sesi_ujian_id' => $sesiId,
                    ],
                    [
                        'ruangan_id' => $request->ruangan_id,
                        'keterangan' => $request->keterangan,
                    ]
                );
            }

            DB::commit();

            return redirect()->route('apps.halangan-rutin')->with('success', 'Jadwal halangan berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('apps.halangan-rutin')->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $refData = DosenHalanganRutin::findOrFail($id);

        $siblings = DosenHalanganRutin::where('dosen_id', $refData->dosen_id)
            ->where('hari', $refData->hari)
            ->where('ruangan_id', $refData->ruangan_id)
            ->where('keterangan', $refData->keterangan)
            ->get();

        $selectedSesi = $siblings->pluck('sesi_ujian_id')->map(function ($id) {
            return (string) $id;
        })->toArray();

        $originalIds = $siblings->pluck('id')->toArray();

        return response()->json([
            'dosen_id' => $refData->dosen_id,
            'hari' => $refData->hari,
            'ruangan_id' => $refData->ruangan_id,
            'keterangan' => $refData->keterangan,
            'selected_sesi' => $selectedSesi,
            'original_ids' => implode(',', $originalIds)
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'dosen_id' => 'required',
            'hari' => 'required',
            'sesi_ujian_ids' => 'required|array',
            'original_ids' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $idsToDelete = explode(',', $request->original_ids);
            DosenHalanganRutin::whereIn('id', $idsToDelete)->delete();

            foreach ($request->sesi_ujian_ids as $sesiId) {
                DosenHalanganRutin::create([
                    'dosen_id' => $request->dosen_id,
                    'hari' => $request->hari,
                    'sesi_ujian_id' => $sesiId,
                    'ruangan_id' => $request->ruangan_id,
                    'keterangan' => $request->keterangan,
                ]);
            }

            DB::commit();
            return redirect()->route('apps.halangan-rutin')->with('success', 'Jadwal berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('apps.halangan-rutin')->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $refData = DosenHalanganRutin::findOrFail($id);

            DosenHalanganRutin::where('dosen_id', $refData->dosen_id)
                ->where('hari', $refData->hari)
                ->where('ruangan_id', $refData->ruangan_id)
                ->where('keterangan', $refData->keterangan)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data halangan rutin berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

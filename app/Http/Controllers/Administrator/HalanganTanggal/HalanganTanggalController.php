<?php

namespace App\Http\Controllers\Administrator\HalanganTanggal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DosenHalanganTanggal;
use App\Models\Dosen;
use App\Models\SesiUjian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HalanganTanggalController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = DosenHalanganTanggal::with(['dosen', 'sesi'])
            ->orderBy('dosen_id')
            ->orderBy('tanggal')
            ->orderBy('sesi_ujian_id');

        $dosenIdLoggedIn = null;

        if ($user->hasRole('Dosen')) {
            $dosenIdLoggedIn = $user->userable_id;
            $query->where('dosen_id', $dosenIdLoggedIn);
        }

        $rawData = $query->get();
        $groupedData = [];
        foreach ($rawData as $row) {

            $key = $row->dosen_id . '-' . $row->tanggal . '-' . md5(trim(strtolower($row->keterangan)));

            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'id' => $row->id,
                    'dosen_nama' => $row->dosen->name ?? '-',
                    'tanggal' => $row->tanggal,
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
            "title" => "Halangan Tanggal (Insidental)",
            'mods' => 'halangan_tanggal',
            'breadcrumb' => [
                ['title' => "Dashboard", 'url' => route('apps.dashboard')],
                ['title' => "Kesediaan Waktu", 'is_active' => true],
                ['title' => "Halangan Tanggal", 'is_active' => true]
            ],
            'dosenIdLoggedIn' => $dosenIdLoggedIn,
            'dataDosen' => Dosen::orderBy('name')->get(),
            'dataSesi' => SesiUjian::where('is_active', 1)->get(),
            'dataHalangan' => collect($groupedData)->values(),
        ];

        return view('administrator.halangan-tanggal.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dosen_id' => 'required',
            'tanggal' => 'required|date',
            'sesi_ujian_ids' => 'required|array',
            'sesi_ujian_ids.*' => 'exists:sesi_ujians,id',
            'keterangan' => 'required',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->sesi_ujian_ids as $sesiId) {
                DosenHalanganTanggal::firstOrCreate(
                    [
                        'dosen_id' => $request->dosen_id,
                        'tanggal' => $request->tanggal,
                        'sesi_ujian_id' => $sesiId,
                    ],
                    [
                        'keterangan' => $request->keterangan,
                    ]
                );
            }

            DB::commit();
            return redirect()->route('apps.halangan-tanggal')->with('success', 'Jadwal halangan tanggal berhasil ditambahkan');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('apps.halangan-tanggal')->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $refData = DosenHalanganTanggal::findOrFail($id);

        $siblings = DosenHalanganTanggal::where('dosen_id', $refData->dosen_id)
            ->where('tanggal', $refData->tanggal)
            ->where('keterangan', $refData->keterangan)
            ->get();

        $selectedSesi = $siblings->pluck('sesi_ujian_id')->map(function($id) {
            return (string) $id;
        })->toArray();

        $originalIds = $siblings->pluck('id')->toArray();

        $response = [
            'dosen_id' => $refData->dosen_id,
            'tanggal' => $refData->tanggal,
            'keterangan' => $refData->keterangan,
            'selected_sesi' => $selectedSesi,
            'original_ids' => implode(',', $originalIds)
        ];

        return response()->json($response);
    }

    public function update(Request $request)
    {
        $request->validate([
            'dosen_id' => 'required',
            'tanggal' => 'required|date',
            'sesi_ujian_ids' => 'required|array',
            'original_ids' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $idsToDelete = explode(',', $request->original_ids);
            DosenHalanganTanggal::whereIn('id', $idsToDelete)->delete();

            foreach ($request->sesi_ujian_ids as $sesiId) {
                DosenHalanganTanggal::create([
                    'dosen_id' => $request->dosen_id,
                    'tanggal' => $request->tanggal,
                    'sesi_ujian_id' => $sesiId,
                    'keterangan' => $request->keterangan,
                ]);
            }

            DB::commit();
            return redirect()->route('apps.halangan-tanggal')->with('success', 'Data berhasil diperbarui');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('apps.halangan-tanggal')->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $refData = DosenHalanganTanggal::findOrFail($id);

            DosenHalanganTanggal::where('dosen_id', $refData->dosen_id)
                ->where('tanggal', $refData->tanggal)
                ->where('keterangan', $refData->keterangan)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Data halangan tanggal berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

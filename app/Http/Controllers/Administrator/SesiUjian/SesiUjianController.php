<?php

namespace App\Http\Controllers\Administrator\SesiUjian;

use App\Models\SesiUjian;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SesiUjianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = [
            "title" => "Sesi Ujian",
            'mods' => 'sesi_ujian',
            'breadcrumbs' => [
                [
                    'title' => 'Dashboard',
                    'url' => route('apps.dashboard')
                ],
                [
                    'title' => 'Master Data',
                    'is_active' => true
                ],
                [
                    'title' => 'Sesi Ujian',
                    'is_active' => true
                ]
            ],
            'dataSesiUjian'   => SesiUjian::all(),
        ];

        return view('administrator.sesi-ujian.index', $data);
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama' => 'required',
                'jam_mulai' => 'required',
                'jam_selesai' => 'required',
            ]);

            SesiUjian::create([
                'nama' => $request->nama,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'is_active' => $request->has('is_active') ? 1 : 0, // Checkbox handling
            ]);

            return redirect()->route('apps.sesi-ujian')->with('success', 'Data berhasil ditambahkan');
        } catch (\Exception $e) {
            return redirect()->route('apps.sesi-ujian')->with('error', $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $data = SesiUjian::find($id);

        echo json_encode($data);
    }

    public function update(Request $request)
    {
        try {
            SesiUjian::where('id', $request->id)->update([
                'nama' => $request->nama,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            return redirect()->route('apps.sesi-ujian')->with('success', 'Data berhasil diupdate');
        } catch (\Exception $e) {
            return redirect()->route('apps.sesi-ujian')->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            SesiUjian::where('id', $id)->delete();
            return $this->successResponse('Berhasil menghapus data');
        } catch (\Exception $e) {
            return $this->exceptionResponse($e);
        }
    }
}

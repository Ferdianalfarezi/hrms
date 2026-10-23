<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Approval HRD (Admin HRMS) atas pengajuan perubahan / penghapusan absensi dari Admin Departemen.
 */
class AbsensiApprovalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        $status = (string) $request->query('status', AbsensiApproval::STATUS_PENDING);
        $status = array_key_exists($status, AbsensiApproval::STATUS) ? $status : '';
        $search = trim((string) $request->query('q'));

        $approvals = AbsensiApproval::query()
            ->with(['karyawan.department', 'requester', 'reviewer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('karyawan', fn ($query) => $query
                ->where(fn ($query) => $query->where('nik', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%"))))
            // Yang menunggu: paling lama diajukan di atas. Yang sudah diputus: terbaru di atas.
            ->when($status === AbsensiApproval::STATUS_PENDING, fn ($query) => $query->oldest(), fn ($query) => $query->latest())
            ->paginate(25)
            ->withQueryString();

        return view('hrms.absensi.approval', [
            'approvals' => $approvals,
            'status' => $status,
            'search' => $search,
            'statusCounts' => AbsensiApproval::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'kodes' => Absensi::KODE,
        ]);
    }

    /**
     * Setujui: perubahan diterapkan ke data absensi (atau data dihapus untuk pengajuan hapus).
     */
    public function approve(Request $request, AbsensiApproval $approval): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');
        $data = $this->validateReview($request);

        if (! $approval->isPending()) {
            return back()->withErrors(['approval' => 'Pengajuan ini sudah diputuskan sebelumnya.']);
        }

        $absensi = $approval->absensi;
        if (! $absensi) {
            return back()->withErrors(['approval' => 'Data absensi yang diajukan sudah dihapus, pengajuan tidak bisa disetujui. Tolak pengajuan ini.']);
        }

        $bentrok = ! $approval->isDeletion() && Absensi::where('karyawan_id', $absensi->karyawan_id)
            ->whereDate('tanggal', $approval->tanggal_baru->toDateString())->whereKeyNot($absensi->id)->exists();
        if ($bentrok) {
            return back()->withErrors(['approval' => 'Karyawan ini sudah punya data absensi lain di tanggal yang diajukan.']);
        }

        DB::transaction(function () use ($approval, $absensi, $request, $data) {
            if ($approval->isDeletion()) {
                $absensi->delete();
            } else {
                $absensi->fill([
                    'tanggal' => $approval->tanggal_baru->toDateString(),
                    'kode' => $approval->kode_baru,
                    'menit_terlambat' => $approval->menit_baru,
                    'keterangan' => $approval->keterangan_baru,
                    'updated_by' => $request->user()->id,
                ])->save();
            }

            $this->decide($approval, AbsensiApproval::STATUS_APPROVED, $request, $data['catatan_review'] ?? null);
        });

        return back()->with('status', "Pengajuan {$approval->karyawan->nik} - {$approval->karyawan->nama} disetujui, absensi sudah diperbarui.");
    }

    /**
     * Tolak: data absensi tetap seperti semula.
     */
    public function reject(Request $request, AbsensiApproval $approval): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');
        $data = $this->validateReview($request);

        if (! $approval->isPending()) {
            return back()->withErrors(['approval' => 'Pengajuan ini sudah diputuskan sebelumnya.']);
        }

        $this->decide($approval, AbsensiApproval::STATUS_REJECTED, $request, $data['catatan_review'] ?? null);

        return back()->with('status', "Pengajuan {$approval->karyawan->nik} - {$approval->karyawan->nama} tidak disetujui, absensi tetap seperti semula.");
    }

    /**
     * File evidence (surat dokter, dll). Bisa dibuka Admin HRMS dan Admin Departemen karyawan tersebut.
     */
    public function evidence(AbsensiApproval $approval): StreamedResponse
    {
        Gate::authorize('update', $approval->karyawan);

        abort_unless($approval->evidence_path && Storage::disk('local')->exists($approval->evidence_path), 404);

        return Storage::disk('local')->response($approval->evidence_path, $approval->evidence_name, [], 'inline');
    }

    /**
     * @return array{catatan_review?: ?string}
     */
    private function validateReview(Request $request): array
    {
        return $request->validate([
            'catatan_review' => ['nullable', 'string', 'max:255'],
        ], [
            'catatan_review.max' => 'Catatan maksimal 255 karakter.',
        ]);
    }

    private function decide(AbsensiApproval $approval, string $status, Request $request, ?string $catatan): void
    {
        $approval->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'catatan_review' => $catatan,
        ]);
    }
}

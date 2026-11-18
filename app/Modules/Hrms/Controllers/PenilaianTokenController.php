<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hrms\Models\PenilaianTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Token penilaian: penilai mengajukan penambahan token (lewat modal profil), Admin HRMS menyetujui / menolak.
 */
class PenilaianTokenController extends Controller
{
    /**
     * Penilai mengajukan penambahan token setelah tokennya habis. Hanya boleh satu pengajuan yang menunggu dalam satu waktu.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('penilaian-tahunan');

        $data = $request->validateWithBag('token', [
            'jumlah' => ['required', 'integer', 'min:1', 'max:'.PenilaianTokenRequest::MAKS_JUMLAH],
            'alasan' => ['required', 'string', 'max:500'],
        ], [], [
            'jumlah' => __('jumlah token'),
            'alasan' => __('alasan'),
        ]);

        $user = $request->user();
        if ($user->token_penilaian > 0) {
            return back()->withErrors(['jumlah' => __('Penambahan token baru bisa diajukan setelah token Anda habis.')], 'token');
        }

        $masihMenunggu = PenilaianTokenRequest::where('user_id', $user->id)->where('status', PenilaianTokenRequest::STATUS_PENDING)->exists();
        if ($masihMenunggu) {
            return back()->withErrors(['jumlah' => __('Masih ada pengajuan token yang menunggu approval HRD.')], 'token');
        }

        PenilaianTokenRequest::create([
            'user_id' => $user->id,
            'jumlah' => $data['jumlah'],
            'alasan' => $data['alasan'],
            'status' => PenilaianTokenRequest::STATUS_PENDING,
        ]);

        return back()->with('status', __('Pengajuan :jumlah token penilaian dikirim ke Admin HRMS.', ['jumlah' => $data['jumlah']]));
    }

    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        $status = (string) $request->query('status', PenilaianTokenRequest::STATUS_PENDING);
        $status = array_key_exists($status, PenilaianTokenRequest::STATUS) ? $status : '';
        $search = trim((string) $request->query('q'));

        $requests = PenilaianTokenRequest::query()
            ->with(['user.karyawan', 'reviewer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($query) => $query
                ->where(fn ($query) => $query->where('nik', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            // Yang menunggu: paling lama diajukan di atas. Yang sudah diputus: terbaru di atas.
            ->when($status === PenilaianTokenRequest::STATUS_PENDING, fn ($query) => $query->oldest(), fn ($query) => $query->latest())
            ->paginate(25)
            ->withQueryString();

        return view('hrms.penilaian.token-approval', [
            'requests' => $requests,
            'status' => $status,
            'search' => $search,
            'statusCounts' => PenilaianTokenRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * Setujui: token penilai bertambah sebanyak yang diajukan.
     */
    public function approve(Request $request, PenilaianTokenRequest $tokenRequest): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');
        $catatan = $this->validateReview($request);

        if (! $tokenRequest->isPending()) {
            return back()->withErrors(['approval' => __('Pengajuan ini sudah diputuskan sebelumnya.')]);
        }

        DB::transaction(function () use ($tokenRequest, $request, $catatan) {
            User::whereKey($tokenRequest->user_id)->increment('token_penilaian', $tokenRequest->jumlah);
            $this->decide($tokenRequest, PenilaianTokenRequest::STATUS_APPROVED, $request->user(), $catatan);
        });

        return back()->with('status', __('Pengajuan :jumlah token :nama disetujui.', ['jumlah' => $tokenRequest->jumlah, 'nama' => $tokenRequest->user->name]));
    }

    public function reject(Request $request, PenilaianTokenRequest $tokenRequest): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');
        $catatan = $this->validateReview($request);

        if (! $tokenRequest->isPending()) {
            return back()->withErrors(['approval' => __('Pengajuan ini sudah diputuskan sebelumnya.')]);
        }

        $this->decide($tokenRequest, PenilaianTokenRequest::STATUS_REJECTED, $request->user(), $catatan);

        return back()->with('status', __('Pengajuan token :nama tidak disetujui.', ['nama' => $tokenRequest->user->name]));
    }

    private function validateReview(Request $request): ?string
    {
        return $request->validate([
            'catatan_review' => ['nullable', 'string', 'max:255'],
        ], [
            'catatan_review.max' => __('Catatan approval maksimal 255 karakter.'),
        ])['catatan_review'] ?? null;
    }

    private function decide(PenilaianTokenRequest $tokenRequest, string $status, User $reviewer, ?string $catatan): void
    {
        $tokenRequest->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'catatan_review' => $catatan,
        ]);
    }
}

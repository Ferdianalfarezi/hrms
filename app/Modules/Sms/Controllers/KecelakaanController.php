<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Controllers\Concerns\ScopesPatrolToDepartment;
use App\Modules\Sms\Models\Kecelakaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Laporan kecelakaan: Work Accident / Traffic Accident (Traffic punya kategori tambahan).
 * Akses sama seperti Daily/Cross Patrol: Admin SMS semua departemen, Admin Departemen departemennya saja.
 */
class KecelakaanController extends Controller
{
    use ScopesPatrolToDepartment;

    public function index(Request $request): View
    {
        Gate::authorize('sms-patrol');

        $user = $request->user();
        $access = $this->accessViewData($user);
        $search = trim((string) $request->query('q'));
        $tipe = (string) $request->query('tipe');
        $department = $access['isAdmin'] ? (string) $request->query('department') : '';

        $kecelakaans = $this->visibleTo(Kecelakaan::query(), $user)
            ->with('department')
            ->when(array_key_exists($tipe, Kecelakaan::TIPE), fn ($query) => $query->where('tipe', $tipe))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nama_korban', 'like', "%{$search}%")
                ->orWhere('deskripsi', 'like', "%{$search}%")))
            ->orderByDesc('waktu')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sms.kecelakaan.index', [
            ...$access,
            'kecelakaans' => $kecelakaans,
            'search' => $search,
            'tipe' => $tipe,
            'department' => $department,
            'tipes' => Kecelakaan::TIPE,
            'kategoris' => Kecelakaan::KATEGORI_TRAFFIC,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('sms-patrol');
        $user = $request->user();
        $data = $this->validated($request);

        Kecelakaan::create([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? null),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sms.kecelakaan.index', $this->keptFilters($request))
            ->with('status', "Kecelakaan {$data['nama_korban']} berhasil ditambahkan.");
    }

    public function update(Request $request, Kecelakaan $kecelakaan): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecord($user, $kecelakaan);
        $data = $this->validated($request);

        $kecelakaan->update([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? $kecelakaan->department_id),
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sms.kecelakaan.index', $this->keptFilters($request))
            ->with('status', "Kecelakaan {$kecelakaan->nama_korban} berhasil diperbarui.");
    }

    public function destroy(Request $request, Kecelakaan $kecelakaan): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $kecelakaan);
        $kecelakaan->delete();

        return redirect()->route('sms.kecelakaan.index', $this->keptFilters($request))
            ->with('status', "Kecelakaan {$kecelakaan->nama_korban} berhasil dihapus.");
    }

    private function photoFolder(): string
    {
        return 'kecelakaan';
    }

    private function filterKeys(): array
    {
        return ['q', 'tipe', 'department', 'page'];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'tipe' => ['required', Rule::in(array_keys(Kecelakaan::TIPE))],
            // Kategori wajib (dan hanya dipakai) untuk Traffic Accident.
            'kategori' => ['nullable', 'required_if:tipe,'.Kecelakaan::TIPE_TRAFFIC, Rule::in(array_keys(Kecelakaan::KATEGORI_TRAFFIC))],
            'waktu' => ['required', 'date_format:Y-m-d\TH:i'],
            'nama_korban' => ['required', 'string', 'max:255'],
            'department_id' => [$request->user()->isModuleAdmin('sms') ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'deskripsi' => ['required', 'string', 'max:5000'],
        ], [
            'required' => ':Attribute wajib diisi.',
            'kategori.required_if' => 'Pilih kategori untuk Traffic Accident.',
            'in' => ':Attribute tidak dikenal.',
            'date_format' => 'Format :attribute tidak valid.',
            'max' => ':Attribute maksimal :max karakter.',
            'department_id.required' => 'Pilih departemen.',
        ], [
            'tipe' => 'tipe kecelakaan',
            'kategori' => 'kategori',
            'waktu' => 'waktu',
            'nama_korban' => 'nama korban',
            'department_id' => 'departemen',
            'deskripsi' => 'deskripsi',
        ]);

        if ($data['tipe'] !== Kecelakaan::TIPE_TRAFFIC) {
            $data['kategori'] = null;
        }
        $data['waktu'] = Carbon::createFromFormat('Y-m-d\TH:i', $data['waktu']);

        return $data;
    }
}

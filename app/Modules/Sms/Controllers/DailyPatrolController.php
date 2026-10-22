<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Controllers\Concerns\ScopesPatrolToDepartment;
use App\Modules\Sms\Models\DailyPatrol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Daily Patrol (temuan K3): Admin Departemen mengisi temuan di departemennya, Admin SMS melihat & mengelola semua.
 * Area = departemen tempat temuan.
 */
class DailyPatrolController extends Controller
{
    use ScopesPatrolToDepartment;

    public function index(Request $request): View
    {
        Gate::authorize('sms-patrol');

        $user = $request->user();
        $access = $this->accessViewData($user);
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $department = $access['isAdmin'] ? (string) $request->query('department') : '';

        $patrols = $this->visibleTo(DailyPatrol::query(), $user)
            ->with('department')
            ->when(in_array($status, [DailyPatrol::STATUS_OPEN, DailyPatrol::STATUS_CLOSED], true), fn ($query) => $query->where('status', $status))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('finding', 'like', "%{$search}%")
                ->orWhere('potensi_bahaya', 'like', "%{$search}%")
                ->orWhere('pic', 'like', "%{$search}%")
                ->orWhere('pic_patrol', 'like', "%{$search}%")))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sms.daily-patrols.index', [
            ...$access,
            'patrols' => $patrols,
            'search' => $search,
            'status' => $status,
            'department' => $department,
            'resikos' => DailyPatrol::RESIKO,
            'kategoris' => DailyPatrol::KATEGORI,
            'karyawans' => $access['canEdit'] ? $this->karyawanOptions() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('sms-patrol');
        $user = $request->user();
        $data = $this->validated($request, null);

        $patrol = DailyPatrol::create([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? null),
            'foto' => $request->file('foto')->store($this->photoFolder(), 'public'),
            'status' => DailyPatrol::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sms.daily-patrols.index')->with('status', "Temuan tanggal {$patrol->tanggal->format('d/m/Y')} berhasil ditambahkan (status Open).");
    }

    public function update(Request $request, DailyPatrol $dailyPatrol): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecord($user, $dailyPatrol);
        [$data, $oldFiles] = $this->applyPhotosAndStatus($request, $dailyPatrol, $this->validated($request, $dailyPatrol));

        $dailyPatrol->fill([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? $dailyPatrol->department_id),
            'updated_by' => $user->id,
        ])->save();
        $this->deletePhotos($oldFiles);

        return redirect()->route('sms.daily-patrols.index', $this->keptFilters($request))
            ->with('status', 'Temuan berhasil diperbarui'.($dailyPatrol->isClosed() ? ' (status Close).' : '.'));
    }

    public function destroy(Request $request, DailyPatrol $dailyPatrol): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $dailyPatrol);

        $dailyPatrol->delete();
        $this->deletePhotos([$dailyPatrol->foto, $dailyPatrol->foto_perbaikan]);

        return redirect()->route('sms.daily-patrols.index')->with('status', 'Temuan berhasil dihapus.');
    }

    private function photoFolder(): string
    {
        return 'daily-patrol';
    }

    private function filterKeys(): array
    {
        return ['q', 'status', 'department', 'page'];
    }

    private function validated(Request $request, ?DailyPatrol $patrol): array
    {
        $creating = $patrol === null;

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'finding' => ['required', 'string', 'max:2000'],
            'foto' => $this->photoRules($creating),
            'potensi_bahaya' => ['required', 'string', 'max:2000'],
            'department_id' => [$request->user()->isModuleAdmin('sms') ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'plant' => ['required', 'string', 'max:50'],
            'rekomendasi' => ['required', 'string', 'max:2000'],
            'resiko' => ['required', Rule::in(array_keys(DailyPatrol::RESIKO))],
            'kategori' => ['required', Rule::in(array_keys(DailyPatrol::kategoriOptions()))],
            'target' => ['required', 'date_format:Y-m-d'],
            'pic' => $this->karyawanNamesRules($patrol?->pic),
            'pic_patrol' => $this->karyawanNamesRules($patrol?->pic_patrol),
            ...($creating ? [] : [
                'foto_perbaikan' => $this->photoRules(false),
                'status' => ['required', Rule::in([DailyPatrol::STATUS_OPEN, DailyPatrol::STATUS_CLOSED])],
            ]),
        ], [...$this->photoMessages(), 'department_id.required' => 'Pilih area.'], [
            'tanggal' => 'tanggal',
            'finding' => 'finding',
            'foto' => 'foto',
            'potensi_bahaya' => 'potensi bahaya',
            'department_id' => 'area',
            'plant' => 'plant',
            'rekomendasi' => 'rekomendasi',
            'resiko' => 'resiko',
            'kategori' => 'kategori',
            'target' => 'target',
            'pic' => 'PIC',
            'pic_patrol' => 'PIC patrol',
            'foto_perbaikan' => 'foto perbaikan',
            'status' => 'status',
        ]);

        $this->ensureCanClose($request, $patrol, $data);
        unset($data['foto'], $data['foto_perbaikan']);

        return $this->joinNames($data, ['pic', 'pic_patrol']);
    }
}

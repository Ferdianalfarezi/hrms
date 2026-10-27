<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sms\Controllers\Concerns\ScopesPatrolToDepartment;
use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Support\CrossPatrolExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Cross Patrol: sama seperti Daily Patrol (Open -> Close setelah ada foto perbaikan), kolom mengikuti
 * "Form Temuan Safety Patrol" dan bisa diexport ke Excel dengan format form tersebut.
 */
class CrossPatrolController extends Controller
{
    use ScopesPatrolToDepartment;

    /** Batas kotak sekali cetak (20 halaman A4) supaya browser tidak berat. */
    private const PRINT_LIMIT = 500;

    public function index(Request $request): View
    {
        Gate::authorize('sms-patrol');

        $access = $this->accessViewData($request->user());
        $filters = $this->filters($request);

        $patrols = $this->filteredQuery($request, $filters)
            ->with('department')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sms.cross-patrols.index', [
            ...$access,
            ...$filters,
            'patrols' => $patrols,
            'plantOptions' => $this->visibleTo(CrossPatrol::query(), $request->user())->distinct()->orderBy('plant')->pluck('plant'),
            'zonas' => CrossPatrol::ZONA,
            'resikos' => CrossPatrol::RESIKO,
            'kategoris' => CrossPatrol::KATEGORI,
            'karyawans' => $access['canEdit'] ? $this->karyawanOptions() : [],
        ]);
    }

    /**
     * Export daftar sesuai filter yang sedang dipakai, urut tanggal (seperti nomor di form).
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('sms-patrol');

        $filters = $this->filters($request);
        $patrols = $this->filteredQuery($request, $filters)->orderBy('tanggal')->orderBy('id')->get();

        return (new CrossPatrolExport(
            $patrols,
            $filters['tanggal'] !== '' ? $filters['tanggal'] : null,
            $filters['plant'] !== '' ? $filters['plant'] : null,
            $filters['zona'] !== '' ? (int) $filters['zona'] : null,
        ))->download();
    }

    /**
     * Kartu temuan siap cetak: temuan yang dicentang (ids[]), atau semua hasil filter kalau tidak ada yang dicentang.
     * Urut tanggal seperti export; nomor kotak = urutan di cetakan.
     */
    public function print(Request $request): View
    {
        Gate::authorize('sms-patrol');

        $ids = collect((array) $request->query('ids'))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id);
        $query = $ids->isNotEmpty()
            ? $this->visibleTo(CrossPatrol::query(), $request->user())->whereIn('id', $ids->take(self::PRINT_LIMIT))
            : $this->filteredQuery($request, $this->filters($request));

        return view('sms.cross-patrols.print', [
            'patrols' => $query->orderBy('tanggal')->orderBy('id')->limit(self::PRINT_LIMIT)->get(),
            // 25 kotak per lembar A4 (5 x 5).
            'perPage' => 25,
            'columns' => 5,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManage($user);
        $data = $this->validated($request, null);

        $patrol = CrossPatrol::create([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? null),
            'foto' => $request->file('foto')->store($this->photoFolder(), 'public'),
            'status' => CrossPatrol::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('sms.cross-patrols.index', $this->keptFilters($request))
            ->with('status', "Temuan tanggal {$patrol->tanggal->format('d/m/Y')} berhasil ditambahkan (status Open).");
    }

    public function update(Request $request, CrossPatrol $crossPatrol): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecord($user, $crossPatrol);
        [$data, $oldFiles] = $this->applyPhotosAndStatus($request, $crossPatrol, $this->validated($request, $crossPatrol));

        $crossPatrol->fill([
            ...$data,
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? $crossPatrol->department_id),
            'updated_by' => $user->id,
        ])->save();
        $this->deletePhotos($oldFiles);

        return redirect()->route('sms.cross-patrols.index', $this->keptFilters($request))
            ->with('status', 'Temuan berhasil diperbarui'.($crossPatrol->isClosed() ? ' (status Close).' : '.'));
    }

    public function destroy(Request $request, CrossPatrol $crossPatrol): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $crossPatrol->delete();
        $this->deletePhotos([$crossPatrol->foto, $crossPatrol->foto_perbaikan]);

        return redirect()->route('sms.cross-patrols.index', $this->keptFilters($request))->with('status', 'Temuan berhasil dihapus.');
    }

    /**
     * @return array{search: string, status: string, department: string, tanggal: string, plant: string, zona: string}
     */
    private function filters(Request $request): array
    {
        $zona = (string) $request->query('zona');
        $tanggal = (string) $request->query('tanggal');
        try {
            $tanggal = $tanggal !== '' ? Carbon::createFromFormat('!Y-m-d', $tanggal)->toDateString() : '';
        } catch (Throwable) {
            $tanggal = '';
        }

        return [
            'search' => trim((string) $request->query('q')),
            'status' => (string) $request->query('status'),
            // Filter departemen: Admin SMS semua, Admin Departemen hanya hasil dalam departemennya (dibatasi visibleTo).
            'department' => (string) $request->query('department'),
            'tanggal' => $tanggal,
            'plant' => trim((string) $request->query('plant')),
            'zona' => in_array((int) $zona, CrossPatrol::ZONA, true) ? (string) (int) $zona : '',
        ];
    }

    private function filteredQuery(Request $request, array $filters): Builder
    {
        $search = $filters['search'];

        return $this->visibleTo(CrossPatrol::query(), $request->user())
            ->when(in_array($filters['status'], [CrossPatrol::STATUS_OPEN, CrossPatrol::STATUS_CLOSED], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(ctype_digit($filters['department']), fn ($query) => $query->where('department_id', $filters['department']))
            ->when($filters['tanggal'] !== '', fn ($query) => $query->whereDate('tanggal', $filters['tanggal']))
            ->when($filters['plant'] !== '', fn ($query) => $query->where('plant', $filters['plant']))
            ->when($filters['zona'] !== '', fn ($query) => $query->where('zona', (int) $filters['zona']))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('finding', 'like', "%{$search}%")
                ->orWhere('area', 'like', "%{$search}%")
                ->orWhere('pic', 'like', "%{$search}%")
                ->orWhere('patroler', 'like', "%{$search}%")));
    }

    private function photoFolder(): string
    {
        return 'cross-patrol';
    }

    private function filterKeys(): array
    {
        return ['q', 'status', 'department', 'tanggal', 'plant', 'zona', 'page'];
    }

    private function validated(Request $request, ?CrossPatrol $patrol): array
    {
        if ($patrol && ! $request->user()->isModuleAdmin('sms')) {
            return $this->validatedFixOnly($request, $patrol);
        }

        $creating = $patrol === null;

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'plant' => ['required', 'string', 'max:50'],
            'zona' => ['required', 'integer', Rule::in(CrossPatrol::ZONA)],
            'finding' => ['required', 'string', 'max:2000'],
            'foto' => $this->photoRules($creating),
            'efek' => ['required', 'string', 'max:2000'],
            'resiko' => ['required', Rule::in(array_keys(CrossPatrol::RESIKO))],
            'area' => ['required', 'string', 'max:100'],
            'department_id' => [$request->user()->isModuleAdmin('sms') ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'rekomendasi' => ['required', 'string', 'max:2000'],
            'target' => ['required', 'date_format:Y-m-d'],
            'pic' => $this->karyawanNamesRules($patrol?->pic),
            'kategori' => ['required', Rule::in(array_keys(DailyPatrol::kategoriOptions()))],
            'patroler' => $this->karyawanNamesRules($patrol?->patroler),
            ...($creating ? [] : [
                'foto_perbaikan' => $this->photoRules(false),
                'status' => ['required', Rule::in([CrossPatrol::STATUS_OPEN, CrossPatrol::STATUS_CLOSED])],
            ]),
        ], $this->photoMessages(), [
            'tanggal' => 'tanggal',
            'plant' => 'plant',
            'zona' => 'zona',
            'finding' => 'finding',
            'foto' => 'foto temuan',
            'efek' => 'efek / impact',
            'resiko' => 'tingkat resiko bahaya',
            'area' => 'area',
            'department_id' => 'departemen',
            'rekomendasi' => 'rekomendasi perbaikan',
            'target' => 'target perbaikan',
            'pic' => 'PIC',
            'kategori' => 'kategori',
            'patroler' => 'patroler',
            'foto_perbaikan' => 'foto perbaikan',
            'status' => 'status',
        ]);

        $this->ensureCanClose($request, $patrol, $data);
        unset($data['foto'], $data['foto_perbaikan']);

        return $this->joinNames($data, ['pic', 'patroler']);
    }
}

<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Models\Kecelakaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        // Master departemen hanya untuk Admin HRMS & super admin; Admin Departemen tidak melihat menu ini.
        Gate::authorize('manage-module', 'hrms');

        $search = trim((string) $request->query('q'));

        $departments = Department::query()
            ->withCount('karyawans')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('hrms.departments.index', [
            'departments' => $departments,
            'search' => $search,
            'canManage' => Gate::allows('manage-module', 'hrms'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $department = Department::create($this->validated($request));

        return redirect()->route('hrms.departments.index')->with('status', "Departemen {$department->name} berhasil dibuat.");
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $department->update($this->validated($request, $department));

        return redirect()->route('hrms.departments.index')->with('status', "Departemen {$department->name} berhasil diperbarui.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        if ($total = $department->karyawans()->count()) {
            return redirect()->route('hrms.departments.index')
                ->with('error', "Departemen {$department->name} masih dipakai {$total} karyawan, pindahkan dulu karyawannya sebelum menghapus.");
        }

        $smsData = DailyPatrol::where('department_id', $department->id)->count()
            + CrossPatrol::where('department_id', $department->id)->count()
            + Kecelakaan::where('department_id', $department->id)->count();
        if ($smsData) {
            return redirect()->route('hrms.departments.index')
                ->with('error', "Departemen {$department->name} masih dipakai {$smsData} data Daily/Cross Patrol & Kecelakaan (SMS), jadi tidak bisa dihapus.");
        }

        $department->delete();

        return redirect()->route('hrms.departments.index')->with('status', "Departemen {$department->name} berhasil dihapus.");
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department)],
        ], [
            'name.required' => 'Nama departemen wajib diisi.',
            'name.max' => 'Nama departemen maksimal 255 karakter.',
            'name.unique' => 'Departemen dengan nama ini sudah ada.',
        ]);
    }
}

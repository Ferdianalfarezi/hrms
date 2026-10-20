<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with('applications')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nik', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderBy('nik')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roleNames' => Role::pluck('name', 'id'),
            'departmentNames' => Department::pluck('name', 'id'),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'applications' => $this->applications(),
            'departments' => Department::orderBy('name')->get(),
            'currentAccess' => [],
            'currentDepartments' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('photo')) {
            $data['attributes']['photo'] = $request->file('photo')->store('photos', 'public');
        }

        DB::transaction(function () use ($data) {
            User::create($data['attributes'])->syncAccess($data['access']);
        });

        return redirect()->route('admin.users.index')->with('status', "User {$data['attributes']['nik']} berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'applications' => $this->applications(),
            'departments' => Department::orderBy('name')->get(),
            'currentAccess' => $user->applications->pluck('pivot.role_id', 'id')->all(),
            'currentDepartments' => $user->applications->pluck('pivot.department_id', 'id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // Jangan sampai super admin mengunci dirinya sendiri.
        if ($user->is($request->user())) {
            $data['attributes']['is_active'] = true;
            $data['attributes']['is_superadmin'] = true;
        }

        $oldPhoto = $user->photo;

        if ($request->hasFile('photo')) {
            $data['attributes']['photo'] = $request->file('photo')->store('photos', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $data['attributes']['photo'] = null;
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data['attributes']);
            $user->syncAccess($data['access']);
        });

        if ($oldPhoto && $oldPhoto !== $user->photo) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()->route('admin.users.index')->with('status', "User {$user->nik} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        return redirect()->route('admin.users.index')->with('status', "User {$user->nik} berhasil dihapus.");
    }

    private function applications()
    {
        return Application::with('roles')->orderBy('sort_order')->get();
    }

    /**
     * @return array{attributes: array<string, mixed>, access: array<int, array{role_id: int, department_id: ?int}>}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('users', 'nik')->ignore($user)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'is_active' => ['boolean'],
            'is_superadmin' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['boolean'],
            'access' => ['array'],
            'access.*' => ['nullable', 'integer', 'exists:roles,id'],
            'department' => ['array'],
            'department.*' => ['nullable', 'integer', 'exists:departments,id'],
        ], [], [
            'nik' => 'NIK',
            'name' => 'nama',
            'password' => 'PIN / password',
            'photo' => 'foto',
        ]);

        // Role harus milik modul yang dipilih, bukan role modul lain.
        $access = collect($validated['access'] ?? [])->filter()->map(fn ($roleId) => (int) $roleId);

        $mismatch = $access->first(fn (int $roleId, $applicationId) => ! Role::whereKey($roleId)->where('application_id', $applicationId)->exists());

        if ($mismatch !== null) {
            throw ValidationException::withMessages(['access' => 'Role tidak sesuai dengan modulnya.']);
        }

        // Role "Admin Departemen" wajib punya departemen; role lain tidak menyimpan departemen.
        $roleCodes = Role::whereIn('id', $access->values())->pluck('code', 'id');
        $access = $access->map(function (int $roleId, $applicationId) use ($roleCodes, $validated) {
            if ($roleCodes[$roleId] !== 'dept_admin') {
                return ['role_id' => $roleId, 'department_id' => null];
            }

            $departmentId = $validated['department'][$applicationId] ?? null;
            if (! $departmentId) {
                throw ValidationException::withMessages(["department.{$applicationId}" => 'Pilih departemen untuk Admin Departemen.']);
            }

            return ['role_id' => $roleId, 'department_id' => (int) $departmentId];
        });

        $attributes = [
            'nik' => $validated['nik'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_superadmin' => $request->boolean('is_superadmin'),
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        return ['attributes' => $attributes, 'access' => $access->all()];
    }
}

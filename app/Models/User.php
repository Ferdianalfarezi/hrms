<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Hrms\Models\Department;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['nik', 'name', 'email', 'photo', 'password', 'is_active', 'is_superadmin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_superadmin' => 'boolean',
        ];
    }

    /**
     * URL foto profil, atau avatar default kalau belum ada foto.
     * Pakai asset() (ikut alamat yang sedang dibuka), bukan Storage::url() yang terkunci ke APP_URL,
     * supaya foto tetap muncul saat aplikasi dibuka lewat IP / komputer lain.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo
            ? asset('storage/'.$this->photo)
            : asset('images/default-avatar.svg'));
    }

    protected function firstName(): Attribute
    {
        return Attribute::get(fn () => Str::before($this->name, ' '));
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'user_app_roles')
            ->withPivot('role_id', 'department_id')
            ->withTimestamps();
    }

    /**
     * Modul aktif yang boleh diakses user, urut sesuai sort_order.
     * Super admin otomatis bisa mengakses semua modul.
     *
     * @return Collection<int, Application>
     */
    public function accessibleApplications(): Collection
    {
        $query = $this->is_superadmin ? Application::query() : $this->applications();

        return $query->active()->orderBy('sort_order')->get();
    }

    public function canAccessApplication(string $code): bool
    {
        $query = $this->is_superadmin ? Application::query() : $this->applications();

        return $query->active()->where('code', $code)->exists();
    }

    /**
     * Role user di modul. Bisa null untuk super admin yang tidak diberi role khusus.
     */
    public function roleFor(string $code): ?Role
    {
        $application = $this->applications()->where('code', $code)->first();

        return $application ? Role::find($application->pivot->role_id) : null;
    }

    /**
     * Label role untuk ditampilkan, mis. "Admin HRMS", "Admin Departemen · PSD", atau "Super Admin".
     */
    public function roleLabel(string $code): ?string
    {
        $application = $this->applications()->where('code', $code)->first();

        if (! $application) {
            return $this->is_superadmin ? 'Super Admin' : null;
        }

        $label = Role::find($application->pivot->role_id)?->name;
        $departmentId = $application->pivot->department_id;

        return $departmentId ? $label.' · '.(Department::find($departmentId)?->name ?? '-') : $label;
    }

    /**
     * Admin penuh sebuah modul: super admin atau role "admin" di modul itu.
     */
    public function isModuleAdmin(string $code): bool
    {
        return $this->is_superadmin
            || ($this->canAccessApplication($code) && $this->roleFor($code)?->code === 'admin');
    }

    /**
     * Departemen yang dipegang user sebagai "Admin Departemen" di modul ini (default HRMS), atau null.
     */
    public function managedDepartmentId(string $code = 'hrms'): ?int
    {
        if ($this->isModuleAdmin($code)) {
            return null;
        }

        $application = $this->applications()->active()->where('code', $code)->first();
        if (! $application || Role::find($application->pivot->role_id)?->code !== 'dept_admin') {
            return null;
        }

        $departmentId = $application->pivot->department_id;

        return $departmentId === null ? null : (int) $departmentId;
    }

    public function isDepartmentAdmin(string $code = 'hrms'): bool
    {
        return ! $this->isModuleAdmin($code) && $this->roleFor($code)?->code === 'dept_admin';
    }

    /**
     * Ganti seluruh akses user sekaligus.
     *
     * @param  array<int, array{role_id: int, department_id?: ?int}>  $access  application_id => pivot
     */
    public function syncAccess(array $access): void
    {
        $this->applications()->sync(
            collect($access)->map(fn (array $pivot) => [
                'role_id' => $pivot['role_id'],
                'department_id' => $pivot['department_id'] ?? null,
            ])->all()
        );
    }

    /**
     * Beri (atau ganti) role user di suatu modul.
     */
    public function assignRole(string $applicationCode, string $roleCode, ?int $departmentId = null): void
    {
        $application = Application::where('code', $applicationCode)->firstOrFail();
        $role = $application->roles()->where('code', $roleCode)->firstOrFail();

        $this->applications()->syncWithoutDetaching([
            $application->id => ['role_id' => $role->id, 'department_id' => $departmentId],
        ]);
    }
}

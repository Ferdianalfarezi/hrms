<?php

namespace App\Modules\Hrms\Support;

use App\Models\Application;
use App\Models\User;
use App\Modules\Hrms\Models\Karyawan;

/**
 * Jenjang jabatan untuk penilaian akhir tahun.
 *
 * Alur: Karyawan dinilai Asst. Leader/Senior Staff, Asst. Leader oleh Leader, Leader oleh Section Chief,
 * Section Chief oleh Asst. Manager/Manager, Asst. Manager/Manager oleh GM.
 * Setelah diisi, hasilnya di-approve bertingkat oleh jenjang di atasnya (lihat JUMLAH_TAHAP).
 *
 * Penilai/approver dicari per jenjang di departemen karyawan. Kalau satu jenjang tidak punya akun di
 * departemen itu, jenjang tersebut dilewati dan diambil jenjang berikutnya.
 */
class JenjangPenilaian
{
    /** Urutan jenjang, makin besar makin tinggi. */
    public const RANK = [
        'karyawan' => 1,
        'asst_leader' => 2,
        'leader' => 3,
        'section' => 4,
        'manager' => 5,
        'gm' => 6,
        'direktur' => 7,
    ];

    public const LABEL = [
        'karyawan' => 'Karyawan',
        'asst_leader' => 'Asst. Leader / Senior Staff',
        'leader' => 'Leader',
        'section' => 'Section Chief',
        'manager' => 'Asst. Manager / Manager',
        'gm' => 'GM',
        'direktur' => 'Direktur',
    ];

    /** Jabatan di data karyawan => jenjang. */
    public const JABATAN = [
        'Karyawan' => 'karyawan',
        'Asst. Leader' => 'asst_leader',
        'Senior Staff' => 'asst_leader',
        'Leader' => 'leader',
        'Section Chief' => 'section',
        'Supervisor' => 'section',
        'Asst. Manager' => 'manager',
        'Manager' => 'manager',
        'GM' => 'gm',
        'Ast. General Manager' => 'gm',
        'Direktur' => 'direktur',
    ];

    /**
     * Banyaknya tahap per jenjang yang dinilai: tahap pertama mengisi penilaian, sisanya approve berurutan.
     * Contoh karyawan: Asst. Leader mengisi, lalu Leader dan Section Chief approve.
     */
    public const JUMLAH_TAHAP = [
        'karyawan' => 3,
        'asst_leader' => 2,
        'leader' => 2,
        'section' => 2,
        'manager' => 1,
    ];

    /** Jenjang tertinggi yang ikut menilai/approve. Direktur tidak masuk alur. */
    private const LEVEL_TERTINGGI = 'gm';

    /** Jenjang terendah yang boleh meng-override approval jenjang di bawahnya. */
    private const LEVEL_OVERRIDE = 'manager';

    /** @var array<string, array<string, true>>|null departemen ("id" / "none") => jenjang yang punya akun */
    private ?array $jenjangPerDepartemen = null;

    public static function levelOf(?string $jabatan): ?string
    {
        return self::JABATAN[$jabatan] ?? null;
    }

    public static function rank(?string $level): int
    {
        return self::RANK[$level] ?? 0;
    }

    public static function label(?string $level): string
    {
        return __(self::LABEL[$level] ?? '-');
    }

    /**
     * Jenjang user: jabatan yang diset di akun (Kelola User), atau jabatan di data karyawan kalau belum diset.
     */
    public static function userLevel(User $user): ?string
    {
        return self::levelOf(self::userJabatan($user));
    }

    public static function userJabatan(User $user): ?string
    {
        return $user->jabatan ?? $user->karyawan?->jabatan;
    }

    /**
     * Departemen tempat user berwenang menilai: departemen yang dipegangnya di HRMS (Admin Departemen),
     * atau departemennya sendiri kalau belum dipilihkan.
     *
     * @return list<int>
     */
    public static function departmentIds(User $user): array
    {
        $applicationId = self::hrmsApplicationId();
        $managed = $user->managedDepartments
            ->filter(fn ($department) => (int) $department->pivot->application_id === $applicationId)
            ->map(fn ($department) => (int) $department->id)
            ->values()
            ->all();

        if ($managed !== []) {
            return $managed;
        }

        $own = $user->karyawan?->department_id;

        return $own ? [(int) $own] : [];
    }

    /**
     * Jabatan yang bisa dinilai (punya form) dan berada di bawah jenjang user.
     *
     * @return list<string>
     */
    public static function jabatanDiBawah(?string $level): array
    {
        return array_values(array_filter(
            array_keys(FormPenilaian::FORM_JABATAN),
            fn (string $jabatan) => self::rank(self::levelOf($jabatan)) < self::rank($level),
        ));
    }

    /**
     * Boleh melihat penilaian karyawan ini: Admin HRMS semua, selain itu jenjang di atasnya dalam departemen yang sama.
     */
    public static function canView(User $user, Karyawan $karyawan): bool
    {
        if ($user->isModuleAdmin('hrms')) {
            return true;
        }

        return self::rank(self::userLevel($user)) > self::rank(self::levelOf($karyawan->jabatan))
            && in_array((int) $karyawan->department_id, self::departmentIds($user), true);
    }

    /**
     * Boleh bertindak sebagai jenjang $level untuk departemen ini.
     */
    public static function actsAs(User $user, ?string $level, ?int $departmentId): bool
    {
        return $level !== null
            && self::userLevel($user) === $level
            && $departmentId !== null
            && in_array($departmentId, self::departmentIds($user), true);
    }

    /**
     * Boleh approve tahap yang sedang menunggu jenjang $level: jenjang itu sendiri, atau Asst. Manager/Manager ke atas
     * yang jenjangnya lebih tinggi (override, mis. departemen tanpa Section Chief aktif).
     */
    public static function canApproveAs(User $user, ?string $level, ?int $departmentId): bool
    {
        if (self::actsAs($user, $level, $departmentId)) {
            return true;
        }

        $userRank = self::rank(self::userLevel($user));

        return $level !== null
            && $departmentId !== null
            && $userRank >= self::RANK[self::LEVEL_OVERRIDE]
            && $userRank > self::rank($level)
            && in_array($departmentId, self::departmentIds($user), true);
    }

    /**
     * Urutan jenjang penilai untuk karyawan: [0] mengisi penilaian, sisanya approver berurutan.
     * Kosong kalau tidak ada satu pun akun penilai di departemennya.
     *
     * @return list<string>
     */
    public function tahapanFor(Karyawan $karyawan): array
    {
        $level = self::levelOf($karyawan->jabatan);
        $jumlah = self::JUMLAH_TAHAP[$level] ?? 0;
        $tersedia = $this->jenjangPerDepartemen()[$karyawan->department_id ?? 'none'] ?? [];

        $kandidat = array_filter(
            array_keys(self::RANK),
            fn (string $calon) => self::rank($calon) > self::rank($level)
                && self::rank($calon) <= self::rank(self::LEVEL_TERTINGGI)
                && isset($tersedia[$calon]),
        );

        return array_slice(array_values($kandidat), 0, $jumlah);
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function jenjangPerDepartemen(): array
    {
        if ($this->jenjangPerDepartemen !== null) {
            return $this->jenjangPerDepartemen;
        }

        $map = [];
        $users = User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('is_superadmin', true)
                ->orWhereHas('applications', fn ($query) => $query->where('code', 'hrms')))
            ->where(fn ($query) => $query->whereNotNull('jabatan')->orWhereHas('karyawan'))
            ->with(['karyawan', 'managedDepartments'])
            ->get();

        foreach ($users as $user) {
            $level = self::userLevel($user);
            if (self::rank($level) < self::rank('asst_leader')) {
                continue;
            }

            foreach (self::departmentIds($user) as $departmentId) {
                $map[$departmentId][$level] = true;
            }
        }

        return $this->jenjangPerDepartemen = $map;
    }

    private static function hrmsApplicationId(): int
    {
        return once(fn () => (int) Application::where('code', 'hrms')->value('id'));
    }
}

<?php

namespace App\Modules\Hrms\Support;

/**
 * Isi form penilaian akhir tahun dan rumus nilainya, mengikuti form HRD/FMR (folder "Form penilaian hr")
 * dan contoh hitungan di "Form penilaian karyawan.xlsx".
 *
 * Nilai akhir = nilai absen + nilai performance (5 kategori), skala 100.
 * - Absen  : (total poin 8 kategori / 40) x bobot absen. Poin per kategori dari tabel KONVERSI_ABSEN.
 * - Kategori performance: (total nilai butir / (jumlah butir x 5)) x bobot kategori. Butir dinilai A=5 ... E=1.
 */
class FormPenilaian
{
    /** Nilai per huruf untuk butir performance. */
    public const NILAI_HURUF = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1];

    /** Batas bawah nilai akhir untuk tiap huruf (RANGE NILAI AKHIR). */
    public const RANGE_HURUF = ['A' => 91, 'B' => 80, 'C' => 60, 'D' => 39, 'E' => 0];

    /**
     * Kategori absen di form: kode absensi yang dihitung + poin berdasarkan banyaknya kejadian.
     * Kolom poin: 0x, 1x, 2x, 3x, 4x, 5x, 6x, 7~10x, 11~15x, >15x.
     *
     * @var array<string, array{label: string, kode: list<string>, poin: list<int>}>
     */
    public const KONVERSI_ABSEN = [
        'A' => ['label' => 'Alpha (A)', 'kode' => ['A'], 'poin' => [5, 0, -2, -4, -6, -8, -10, -12, -14, -16]],
        'I' => ['label' => 'Ijin (I)', 'kode' => ['I'], 'poin' => [5, 3, 2, 1, 0, 0, 0, 0, 0, 0]],
        'S' => ['label' => 'Sakit (S)', 'kode' => ['S'], 'poin' => [5, 4, 2, 1, 0, 0, 0, 0, 0, 0]],
        'DT' => ['label' => 'Datang Telat (DT)', 'kode' => ['DT<'], 'poin' => [5, 5, 4, 4, 3, 2, 1, 0, 0, 0]],
        'DT30' => ['label' => 'Datang Telat (DT) > 30 mnt', 'kode' => ['DT>'], 'poin' => [5, 4, 3, 2, 1, 1, 0, 0, 0, 0]],
        'PC' => ['label' => 'Pulang Cepat (PC)', 'kode' => ['PC<'], 'poin' => [5, 5, 4, 4, 3, 2, 1, 0, 0, 0]],
        'PC30' => ['label' => 'Pulang Cepat (PC) > 30 mnt', 'kode' => ['PC>'], 'poin' => [5, 4, 3, 2, 1, 1, 0, 0, 0, 0]],
        'SD' => ['label' => 'Sakit Dokter (SD)', 'kode' => ['SD'], 'poin' => [5, 5, 4, 4, 3, 2, 1, 0, 0, 0]],
    ];

    /** Nilai maksimum absen: 8 kategori x 5. */
    public const MAKS_ABSEN = 40;

    /** Jabatan karyawan => form yang dipakai. */
    public const FORM_JABATAN = [
        'Karyawan' => 'operator',
        'Asst. Leader' => 'asst_leader',
        'Senior Staff' => 'asst_leader',
        'Leader' => 'leader',
        'Section Chief' => 'section',
        'Supervisor' => 'section',
        'Asst. Manager' => 'section',
        'Manager' => 'manager',
    ];

    /** Butir form Leader, Section Chief/Asst. Manager, dan Manager sama persis; yang berbeda hanya bobotnya. */
    private const BUTIR_MANAGERIAL = [
        'kemampuan' => [
            'Mengerti dan selalu bekerja sesuai prosedur yang ada',
            'Paham dan memperhatikan faktor keselamatan kerja',
            'Mampu/bisa menghilangkan pemborosan/muda (managerial)',
            'Mampu melakukan perbaikan/kaizen (managerial)',
            'Rapi/teliti dalam bekerja dan mampu/bisa mengajarkan',
            'Mampu menyusun rencana/schedule kerja',
            'Mampu mengambil keputusan dengan cepat & tepat',
            'Bisa membuat standard kerja dalam group/section (managerial)',
            'Kuat dalam menghadapi tekanan kerja dan selalu serius',
        ],
        'sikap' => [
            'Mematuhi peraturan perusahaan yang berlaku',
            'Ulet dan percaya diri dalam bekerja',
            'Tepat waktu dalam bekerja/meeting',
            'Tidak berdalih dengan alasan macam-macam/sesuai perintah',
            'Memanfaatkan waktu kerja secara efektif (managerial)',
            'Bisa menjadi contoh tauladan yang baik pada bawahan/rekan',
            'Mendorong bawahan bekerja lebih aktif',
            'Melakukan bimbingan kepada bawahan',
        ],
        'tanggung_jawab' => [
            'Sehat fisik ataupun mental dalam bekerja',
            'Arsip/dokumen/alat perlengkapan kerja rapi',
            'Pakaian, sepatu, topi dan atribut kerja lengkap dan rapi',
            'Mengontrol disiplin kerja bawahan',
            'Mampu memarahi/menegur karyawan yang bermasalah',
            'Penampilan diri dan kemampuan di dalam dan luar perusahaan',
        ],
        'hubungan' => [
            'Menciptakan kerjasama yang baik antar group kerja',
            'Cekatan menyediakan informasi/horenso berupa data pada atasan dan rekan kerja',
            'Terbuka menerima saran dari atasan/rekan kerja',
            'Menerima informasi/instruksi kerja dengan jelas',
            'Disenangi oleh pimpinan/atasan/rekan dan karyawan',
            'Kemampuan berkomunikasi yang baik dan kuat dalam negosiasi',
        ],
        'kreativitas' => [
            'Menemukan ide-ide yang baru dalam bekerja',
            'Mau mempelajari seluk beluk/prosedur pekerjaan dan lingkungan',
            'Memperbaiki kesalahan kerja dengan cara yang benar',
            'Mau banyak bertanya untuk hal-hal yang tidak/belum dimengerti',
            'Adanya peningkatan/perbaikan hasil kerja dari waktu ke waktu',
            'Memahami problem yang menjadi tanggung jawab diri sendiri dalam perusahaan dan berpikir berdasarkan teori/logic dan fleksibilitas penggunaan',
        ],
    ];

    /** Judul kategori performance sesuai urutan di form (II s/d VI). */
    public const KATEGORI = [
        'kemampuan' => 'II. Kemampuan Kerja',
        'sikap' => 'III. Sikap Kerja',
        'tanggung_jawab' => 'IV. Tanggung Jawab',
        'hubungan' => 'V. Hubungan Kerja',
        'kreativitas' => 'VI. Kreativitas',
    ];

    /**
     * @return array{label: string, dokumen: string, judul: string, tanggal_efektif: string, revisi: string, bobot_absen: int, bobot_kategori: int, butir: array<string, list<string>>}
     */
    public static function form(string $key): array
    {
        return self::forms()[$key];
    }

    /**
     * @return array<string, array{label: string, dokumen: string, judul: string, tanggal_efektif: string, revisi: string, bobot_absen: int, bobot_kategori: int, butir: array<string, list<string>>}>
     */
    public static function forms(): array
    {
        return [
            'operator' => [
                'label' => 'Penilaian Operator',
                'dokumen' => 'HRD/FMR/7',
                'judul' => 'KARYAWAN LEVEL OPERATOR',
                'tanggal_efektif' => '28 Maret 2016',
                'revisi' => '07',
                'bobot_absen' => 20,
                'bobot_kategori' => 16,
                'butir' => [
                    'kemampuan' => [
                        'Mengerti dan selalu bekerja sesuai prosedur yang ada',
                        'Paham dan memperhatikan faktor keselamatan kerja',
                        'Rapi dan teliti dalam bekerja',
                        'Tidak melakukan kesalahan yang sama berulang',
                    ],
                    'sikap' => [
                        'Mematuhi aturan perusahaan yang berlaku',
                        'Ulet, tekun dan percaya diri',
                        'Tepat waktu kerja',
                        'Tidak berdalih dengan alasan macam-macam',
                    ],
                    'tanggung_jawab' => [
                        'Pakaian, sepatu dan atribut kerja lengkap dan rapi',
                        'Melaksanakan lembur dengan semangat',
                        'Serius dalam menyelesaikan pekerjaan',
                    ],
                    'hubungan' => [
                        'Menciptakan kerjasama yang baik dalam group kerja',
                        'Cekatan menyediakan informasi/data pada atasan',
                        'Terbuka menerima saran dari atasan, rekan',
                        'Menerima informasi/instruksi dengan benar',
                        'Disenangi atasan dan rekan',
                    ],
                    'kreativitas' => [
                        'Menemukan ide-ide baru dalam bekerja',
                        'Mau belajar seluk beluk pekerjaan dan lingkungan',
                        'Memperbaiki kesalahan dengan cara yang benar',
                        'Mau bertanya untuk hal yang tidak/belum dimengerti',
                    ],
                ],
            ],
            'asst_leader' => [
                'label' => 'Penilaian Asst. Leader',
                'dokumen' => 'HRD/FMR/34',
                'judul' => 'AST. LEADER',
                'tanggal_efektif' => '28 Maret 2016',
                'revisi' => '01',
                'bobot_absen' => 20,
                'bobot_kategori' => 16,
                'butir' => [
                    'kemampuan' => [
                        'Mengerti dan selalu bekerja sesuai prosedur yang ada',
                        'Paham dan memperhatikan faktor keselamatan kerja',
                        'Efisien dalam pemakaian bahan-bahan/material',
                        'Rapi dan teliti dalam bekerja',
                        'Tidak melakukan kesalahan yang sama berulang',
                        'Mampu menyusun rencana/schedule kerja',
                        'Mampu mengambil keputusan dengan cepat & tepat',
                    ],
                    'sikap' => [
                        'Mematuhi aturan perusahaan yang berlaku',
                        'Ulet, tekun dan percaya diri',
                        'Tepat waktu kerja, meeting dll',
                        'Tidak berdalih dengan alasan macam-macam',
                        'Memanfaatkan waktu kerja secara efektif',
                        'Menjadi contoh tauladan bawahan & rekan',
                        'Mendorong bawahan bekerja aktif',
                    ],
                    'tanggung_jawab' => [
                        'Arsip, alat perlengkapan kerja rapi',
                        'Pakaian, sepatu dan atribut kerja lengkap dan rapi',
                        'Melaksanakan lembur dengan semangat',
                        'Serius dalam menyelesaikan pekerjaan',
                        'Mengontrol disiplin kerja bawahan',
                    ],
                    'hubungan' => [
                        'Menciptakan kerjasama yang baik dalam group kerja',
                        'Cekatan menyediakan informasi/data pada atasan',
                        'Terbuka menerima saran dari atasan, rekan',
                        'Menerima informasi/instruksi dengan benar',
                        'Disenangi atasan dan rekan',
                    ],
                    'kreativitas' => [
                        'Menemukan ide-ide baru dalam bekerja',
                        'Mau belajar seluk beluk pekerjaan dan lingkungan',
                        'Memperbaiki kesalahan dengan cara yang benar',
                        'Mau bertanya untuk hal yang tidak/belum dimengerti',
                        'Adanya peningkatan hasil kerja dari waktu ke waktu',
                    ],
                ],
            ],
            'leader' => [
                'label' => 'Penilaian Leader',
                'dokumen' => 'HRD/FMR/19',
                'judul' => 'KARYAWAN LEVEL LEADER',
                'tanggal_efektif' => '28 Maret 2016',
                'revisi' => '06',
                'bobot_absen' => 20,
                'bobot_kategori' => 16,
                'butir' => self::BUTIR_MANAGERIAL,
            ],
            'section' => [
                'label' => 'Penilaian Section Chief & Asst. Manager',
                'dokumen' => 'HRD/FMR/35',
                'judul' => 'KARYAWAN LEVEL SECTION CHIEF & AST. MANAGER',
                'tanggal_efektif' => '30 Oktober 2019',
                'revisi' => '04',
                'bobot_absen' => 15,
                'bobot_kategori' => 17,
                'butir' => self::BUTIR_MANAGERIAL,
            ],
            'manager' => [
                'label' => 'Penilaian Manager',
                'dokumen' => 'HRD/FMR/36',
                'judul' => 'KARYAWAN LEVEL MANAGER',
                'tanggal_efektif' => '30 Oktober 2019',
                'revisi' => '04',
                'bobot_absen' => 10,
                'bobot_kategori' => 18,
                'butir' => self::BUTIR_MANAGERIAL,
            ],
        ];
    }

    public static function formForJabatan(?string $jabatan): ?string
    {
        return self::FORM_JABATAN[$jabatan] ?? null;
    }

    /**
     * Poin satu kategori absen berdasarkan banyaknya kejadian dalam setahun.
     */
    public static function poinAbsen(string $kategori, int $jumlah): int
    {
        return self::KONVERSI_ABSEN[$kategori]['poin'][self::kolomAbsen($jumlah)];
    }

    /**
     * Kolom tabel konversi untuk banyaknya kejadian: 0-6 = kolomnya sendiri, 7~10 = 7, 11~15 = 8, ≥16 = 9.
     */
    public static function kolomAbsen(int $jumlah): int
    {
        return match (true) {
            $jumlah <= 6 => max($jumlah, 0),
            $jumlah <= 10 => 7,
            $jumlah <= 15 => 8,
            default => 9,
        };
    }

    /**
     * Rincian nilai absen dari jumlah kejadian per kategori.
     *
     * @param  array<string, int>  $jumlah  kategori absen => banyaknya kejadian
     * @return array{rincian: array<string, array{jumlah: int, poin: int}>, total_poin: int, nilai: float}
     */
    public static function hitungAbsen(array $jumlah, int $bobot): array
    {
        $rincian = [];
        foreach (array_keys(self::KONVERSI_ABSEN) as $kategori) {
            $count = (int) ($jumlah[$kategori] ?? 0);
            $rincian[$kategori] = ['jumlah' => $count, 'poin' => self::poinAbsen($kategori, $count)];
        }

        $totalPoin = array_sum(array_column($rincian, 'poin'));

        return [
            'rincian' => $rincian,
            'total_poin' => $totalPoin,
            'nilai' => round($totalPoin / self::MAKS_ABSEN * $bobot, 2),
        ];
    }

    /**
     * Nilai tiap kategori performance dari huruf A-E per butir. Butir yang belum diisi dihitung 0.
     *
     * @param  array<string, array<int, string|null>>  $skor  kategori => [index butir => huruf]
     * @return array{kategori: array<string, array{total: int, maks: int, nilai: float}>, nilai: float, lengkap: bool}
     */
    public static function hitungPerformance(string $formKey, array $skor): array
    {
        $form = self::form($formKey);
        $hasil = [];
        $lengkap = true;

        foreach ($form['butir'] as $kategori => $butir) {
            $total = 0;
            foreach (array_keys($butir) as $index) {
                $huruf = $skor[$kategori][$index] ?? null;
                if (! isset(self::NILAI_HURUF[$huruf])) {
                    $lengkap = false;

                    continue;
                }
                $total += self::NILAI_HURUF[$huruf];
            }

            $maks = count($butir) * 5;
            $hasil[$kategori] = ['total' => $total, 'maks' => $maks, 'nilai' => round($total / $maks * $form['bobot_kategori'], 2)];
        }

        return [
            'kategori' => $hasil,
            'nilai' => round(array_sum(array_column($hasil, 'nilai')), 2),
            'lengkap' => $lengkap,
        ];
    }

    /**
     * Huruf nilai akhir. Nilai dibulatkan dulu karena range di form berupa bilangan bulat (mis. 80 ~ 90).
     */
    public static function huruf(float $nilaiAkhir): string
    {
        $bulat = (int) round($nilaiAkhir);

        foreach (self::RANGE_HURUF as $huruf => $batas) {
            if ($bulat >= $batas) {
                return $huruf;
            }
        }

        return 'E';
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Models\IsoFolder;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsoDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
    }

    private function isoAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('iso', 'admin');

        return $user;
    }

    private function deptAdmin(Department $department): User
    {
        $user = User::factory()->create();
        $user->assignRole('iso', 'dept_admin', $department->id);

        return $user;
    }

    private function document(?Department $department, array $overrides = []): IsoDocument
    {
        $document = IsoDocument::factory()->create(['department_id' => $department?->id, ...$overrides]);
        Storage::disk('local')->put($document->path, 'isi file');

        return $document;
    }

    public function test_iso_admin_uploads_several_files_of_any_type(): void
    {
        $this->actingAs($this->isoAdmin())->post(route('iso.procedures.store'), [
            'files' => [
                UploadedFile::fake()->create('Prosedur Produksi.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('Daftar Hadir.xlsx', 40),
                UploadedFile::fake()->create('catatan.zip', 10),
            ],
            'department_id' => $this->psd->id,
        ])->assertRedirect(route('iso.procedures.index'))->assertSessionHas('status', '3 file berhasil diupload ke Procedures.');

        $documents = IsoDocument::orderBy('id')->get();
        $this->assertSame(['Prosedur Produksi.pdf', 'Daftar Hadir.xlsx', 'catatan.zip'], $documents->pluck('nama')->all());
        $this->assertSame(['pdf', 'xlsx', 'zip'], $documents->pluck('extension')->all());
        $this->assertSame(['procedures'], $documents->pluck('kategori')->unique()->values()->all());
        $this->assertSame([$this->psd->id], $documents->pluck('department_id')->unique()->values()->all());
        $documents->each(fn (IsoDocument $document) => Storage::disk('local')->assertExists($document->path));
    }

    public function test_upload_requires_a_file_and_respects_size_limit(): void
    {
        $admin = $this->isoAdmin();

        $this->actingAs($admin)->post(route('iso.forms.store'), [])
            ->assertSessionHasErrors(['files' => 'Pilih minimal satu file.']);
        $this->actingAs($admin)->post(route('iso.forms.store'), ['files' => [UploadedFile::fake()->create('besar.pdf', IsoDocument::MAX_KB + 1)]])
            ->assertSessionHasErrors(['files.0' => 'Ukuran tiap file maksimal 50 MB.']);

        $this->assertSame(0, IsoDocument::count());
    }

    public function test_department_admin_sees_own_department_and_general_documents_only(): void
    {
        $this->document($this->psd, ['nama' => 'Dok PSD.pdf']);
        $this->document($this->pwd, ['nama' => 'Dok PWD.pdf']);
        $this->document(null, ['nama' => 'Dok Umum.pdf']);
        $this->document($this->psd, ['nama' => 'Form PSD.pdf', 'kategori' => 'forms']);

        $this->actingAs($this->deptAdmin($this->psd))->get(route('iso.procedures.index'))
            ->assertOk()
            ->assertSee('Dok PSD.pdf')
            ->assertSee('Dok Umum.pdf')
            ->assertDontSee('Dok PWD.pdf')
            ->assertDontSee('Form PSD.pdf'); // kategori lain tampil di menu Forms
    }

    public function test_user_without_iso_access_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('iso.procedures.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('iso.dashboard'))->assertForbidden();
    }

    public function test_dashboard_counts_follow_access(): void
    {
        $this->document($this->psd, ['nama' => 'Prosedur PSD.pdf']);
        $this->document($this->pwd, ['nama' => 'Prosedur PWD.pdf']);
        $this->document(null, ['nama' => 'Form Umum.xlsx', 'kategori' => 'forms', 'extension' => 'xlsx']);

        $admin = $this->isoAdmin();
        $this->actingAs($admin)->get(route('iso.dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, '.$admin->first_name)
            ->assertSee($admin->photo_url, false)
            ->assertViewHas('bigStats', fn (array $stats) => $stats[0]['value'] === 3)
            ->assertViewHas('pills', fn (array $pills) => array_column($pills, 'count', 'label') === ['Procedures' => 2, 'Forms' => 1])
            ->assertSee('Prosedur PWD.pdf');

        // Admin Departemen PSD: dokumen PSD + dokumen umum saja.
        $this->actingAs($this->deptAdmin($this->psd))->get(route('iso.dashboard'))
            ->assertOk()
            ->assertViewHas('bigStats', fn (array $stats) => $stats[0]['value'] === 2)
            ->assertSee('Prosedur PSD.pdf')
            ->assertDontSee('Prosedur PWD.pdf');
    }

    public function test_guest_only_sees_active_general_documents_and_cannot_change_anything(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole('iso', 'guest');
        $umum = $this->document(null, ['nama' => 'Kebijakan Mutu.pdf']);
        $umumNonaktif = $this->document(null, ['nama' => 'Kebijakan Lama.pdf', 'is_active' => false, 'path' => 'iso/procedures/lama.pdf']);
        $psd = $this->document($this->psd, ['nama' => 'Prosedur PSD.pdf', 'path' => 'iso/procedures/psd.pdf']);
        $folderUmum = $this->folder(null, ['nama' => 'Folder Umum']);
        $this->folder($this->psd, ['nama' => 'Folder PSD']);

        $this->actingAs($guest)->get(route('iso.procedures.index'))
            ->assertOk()
            ->assertSee('Kebijakan Mutu.pdf')
            ->assertSee('Folder Umum')
            ->assertSee('hanya dokumen umum')
            ->assertDontSee('Kebijakan Lama.pdf')
            ->assertDontSee('Prosedur PSD.pdf')
            ->assertDontSee('Folder PSD')
            ->assertDontSee('+ Upload File')
            ->assertDontSee('+ Folder');

        $this->actingAs($guest)->get(route('iso.procedures.file', $umum))->assertOk();
        $this->actingAs($guest)->get(route('iso.procedures.file', $umumNonaktif))->assertForbidden();
        $this->actingAs($guest)->get(route('iso.procedures.file', $psd))->assertForbidden();
        // Guest tidak punya dashboard: langsung ke Procedures, tab Dashboard tidak tampil.
        $this->actingAs($guest)->get(route('iso.dashboard'))->assertRedirect(route('iso.procedures.index'));
        $this->actingAs($guest)->get(route('iso.procedures.index'))
            ->assertSee('href="'.route('iso.procedures.index').'" class="subtab', false)
            ->assertDontSee('href="'.route('iso.dashboard').'" class="subtab', false);

        // Tidak bisa mengubah apa pun.
        $this->actingAs($guest)->post(route('iso.procedures.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 5)]])->assertForbidden();
        $this->actingAs($guest)->post(route('iso.procedures.folders.store'), ['nama' => 'Baru'])->assertForbidden();
        $this->actingAs($guest)->put(route('iso.procedures.update', $umum), ['nama' => 'Ganti'])->assertForbidden();
        $this->actingAs($guest)->delete(route('iso.procedures.destroy', $umum))->assertForbidden();
        $this->actingAs($guest)->delete(route('iso.procedures.folders.destroy', $folderUmum))->assertForbidden();
        $this->actingAs($guest)->patchJson(route('iso.procedures.status', $umum), ['is_active' => false])->assertForbidden();
        $this->actingAs($guest)->get(route('iso.emails.index'))->assertForbidden();

        $this->assertSame(3, IsoDocument::count());
        $this->assertSame('Kebijakan Mutu.pdf', $umum->fresh()->nama);
    }

    public function test_department_admin_can_only_view_procedures(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);
        $prosedur = $this->document($this->psd, ['nama' => 'Prosedur PSD.pdf']);
        $folder = $this->folder($this->psd, ['nama' => 'Folder Prosedur']);

        $this->actingAs($deptAdmin)->get(route('iso.procedures.index'))
            ->assertOk()
            ->assertSee('Prosedur PSD.pdf')
            ->assertSee('Hanya lihat')
            ->assertDontSee('+ Upload File')
            ->assertDontSee('data-edit="'.$prosedur->id.'"', false)
            ->assertDontSee('data-edit-folder="'.$folder->id.'"', false);
        $this->actingAs($deptAdmin)->get(route('iso.procedures.file', $prosedur))->assertOk();

        $this->actingAs($deptAdmin)->post(route('iso.procedures.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 5)]])->assertForbidden();
        $this->actingAs($deptAdmin)->post(route('iso.procedures.folders.store'), ['nama' => 'Baru'])->assertForbidden();
        $this->actingAs($deptAdmin)->put(route('iso.procedures.update', $prosedur), ['nama' => 'Ganti'])->assertForbidden();
        $this->actingAs($deptAdmin)->delete(route('iso.procedures.destroy', $prosedur))->assertForbidden();
        $this->actingAs($deptAdmin)->put(route('iso.procedures.folders.update', $folder), ['nama' => 'Ganti'])->assertForbidden();
        $this->actingAs($deptAdmin)->delete(route('iso.procedures.folders.destroy', $folder))->assertForbidden();

        $this->assertSame('Prosedur PSD.pdf', $prosedur->fresh()->nama);
        $this->assertModelExists($folder);

        // Di Forms & SOP tetap bisa upload.
        $this->actingAs($deptAdmin)->get(route('iso.forms.index'))->assertSee('+ Upload File')->assertDontSee('Hanya lihat');
    }

    public function test_department_admin_uploads_only_to_own_department(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);

        $this->actingAs($deptAdmin)->post(route('iso.sop.store'), ['files' => [UploadedFile::fake()->create('SOP Mesin.docx', 20)]])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->psd->id, IsoDocument::sole()->department_id);

        $this->actingAs($deptAdmin)->post(route('iso.sop.store'), [
            'files' => [UploadedFile::fake()->create('SOP Lain.docx', 20)],
            'department_id' => $this->pwd->id,
        ])->assertSessionHasErrors(['department_id' => 'Pilih salah satu departemen yang Anda kelola.']);
        $this->assertSame(1, IsoDocument::count());
    }

    public function test_department_admin_cannot_change_general_or_other_department_documents(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);
        $umum = $this->document(null);
        $pwd = $this->document($this->pwd);

        $this->actingAs($deptAdmin)->put(route('iso.procedures.update', $umum), ['nama' => 'Ganti'])->assertForbidden();
        $this->actingAs($deptAdmin)->delete(route('iso.procedures.destroy', $umum))->assertForbidden();
        $this->actingAs($deptAdmin)->delete(route('iso.procedures.destroy', $pwd))->assertForbidden();
        $this->actingAs($deptAdmin)->get(route('iso.procedures.file', $pwd))->assertForbidden();

        $this->assertSame(2, IsoDocument::count());
    }

    public function test_rename_keeps_extension_and_replacing_file_removes_old_one(): void
    {
        $document = $this->document($this->psd, ['nama' => 'Lama.pdf', 'kategori' => 'sop']);
        $oldPath = $document->path;

        $this->actingAs($this->deptAdmin($this->psd))->put(route('iso.sop.update', $document), [
            'nama' => 'Prosedur Baru',
            'file' => UploadedFile::fake()->create('revisi.docx', 30),
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('Prosedur Baru.docx', $document->nama);
        $this->assertSame('docx', $document->extension);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_delete_removes_record_and_file(): void
    {
        $document = $this->document($this->psd, ['kategori' => 'forms']);

        $this->actingAs($this->deptAdmin($this->psd))->delete(route('iso.forms.destroy', $document))
            ->assertRedirect(route('iso.forms.index'));

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_pdf_opens_in_browser_but_other_types_are_downloaded(): void
    {
        $admin = $this->isoAdmin();
        $pdf = $this->document(null, ['nama' => 'Manual.pdf']);
        $html = $this->document(null, ['nama' => 'halaman.html', 'extension' => 'html', 'path' => 'iso/procedures/x.html']);

        $this->actingAs($admin)->get(route('iso.procedures.file', $pdf))
            ->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Manual.pdf');
        $this->actingAs($admin)->get(route('iso.procedures.file', $pdf).'?download=1')
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=Manual.pdf');
        $this->actingAs($admin)->get(route('iso.procedures.file', $html))
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=halaman.html')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_document_is_only_reachable_through_its_own_menu(): void
    {
        $form = $this->document(null, ['kategori' => 'forms', 'path' => 'iso/forms/x.pdf']);

        $this->actingAs($this->isoAdmin())->get(route('iso.procedures.file', $form))->assertNotFound();
        $this->actingAs($this->isoAdmin())->get(route('iso.forms.file', $form))->assertOk();
    }

    public function test_inactive_documents_are_hidden_from_department_admins(): void
    {
        $sop1 = $this->folder($this->psd, ['nama' => 'SOP1', 'kategori' => 'sop']);
        $aktif = $this->document($this->psd, ['nama' => 'SOP Aktif.pdf', 'kategori' => 'sop', 'folder_id' => $sop1->id]);
        $nonaktif = $this->document($this->psd, ['nama' => 'SOP Lama.pdf', 'kategori' => 'sop', 'folder_id' => $sop1->id, 'is_active' => false, 'path' => 'iso/sop/lama.pdf']);
        $deptAdmin = $this->deptAdmin($this->psd);

        $this->actingAs($deptAdmin)->get(route('iso.sop.index', ['folder' => $sop1->id]))
            ->assertOk()->assertSee('SOP Aktif.pdf')->assertDontSee('SOP Lama.pdf');
        $this->actingAs($deptAdmin)->get(route('iso.sop.index'))->assertSee('PSD · 1 file');
        $this->actingAs($deptAdmin)->get(route('iso.sop.file', $nonaktif))->assertForbidden();
        $this->actingAs($deptAdmin)->get(route('iso.dashboard'))->assertViewHas('bigStats', fn (array $stats) => $stats[0]['value'] === 1);

        // Admin ISO tetap melihat semuanya, dokumen nonaktif ditandai.
        $this->actingAs($this->isoAdmin())->get(route('iso.sop.index', ['folder' => $sop1->id]))
            ->assertOk()->assertSee('SOP Aktif.pdf')->assertSee('SOP Lama.pdf')->assertSee('doc-card inactive', false);

        $this->assertTrue($aktif->fresh()->is_active);
    }

    public function test_only_iso_admin_can_toggle_status(): void
    {
        $document = $this->document($this->psd);

        $this->actingAs($this->deptAdmin($this->psd))->patchJson(route('iso.procedures.status', $document), ['is_active' => false])->assertForbidden();
        $this->assertTrue($document->fresh()->is_active);

        $this->actingAs($this->isoAdmin())->patchJson(route('iso.procedures.status', $document), ['is_active' => false])
            ->assertOk()->assertJson(['is_active' => false]);
        $this->assertFalse($document->fresh()->is_active);
    }

    public function test_department_admin_cannot_delete_folder_with_hidden_inactive_documents(): void
    {
        $folder = $this->folder($this->psd);
        $this->document($this->psd, ['folder_id' => $folder->id, 'is_active' => false]);

        $this->actingAs($this->deptAdmin($this->psd))->delete(route('iso.procedures.folders.destroy', $folder))->assertForbidden();

        $this->assertModelExists($folder);
    }

    private function folder(?Department $department, array $overrides = []): IsoFolder
    {
        return IsoFolder::factory()->create(['department_id' => $department?->id, ...$overrides]);
    }

    public function test_folder_contents_follow_the_folder_department(): void
    {
        $admin = $this->isoAdmin();

        $this->actingAs($admin)->post(route('iso.procedures.folders.store'), ['nama' => 'Produksi', 'department_id' => $this->psd->id])
            ->assertSessionHasNoErrors();
        $produksi = IsoFolder::sole();
        $this->assertSame($this->psd->id, $produksi->department_id);

        // Subfolder & file di dalam folder ikut departemen folder, walaupun form mengirim departemen lain.
        $this->actingAs($admin)->post(route('iso.procedures.folders.store'), ['nama' => 'Revisi', 'parent_id' => $produksi->id, 'department_id' => $this->pwd->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('iso.procedures.store'), [
            'files' => [UploadedFile::fake()->create('IK Mesin.pdf', 20)],
            'folder_id' => $produksi->id,
            'department_id' => $this->pwd->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->psd->id, IsoFolder::where('nama', 'Revisi')->value('department_id'));
        $document = IsoDocument::sole();
        $this->assertSame($produksi->id, $document->folder_id);
        $this->assertSame($this->psd->id, $document->department_id);
    }

    public function test_folder_page_lists_only_its_own_contents(): void
    {
        $produksi = $this->folder($this->psd, ['nama' => 'Produksi']);
        $revisi = $this->folder($this->psd, ['nama' => 'Revisi', 'parent_id' => $produksi->id]);
        $this->document($this->psd, ['nama' => 'Di Root.pdf']);
        $this->document($this->psd, ['nama' => 'Di Produksi.pdf', 'folder_id' => $produksi->id]);
        $this->document($this->psd, ['nama' => 'Di Revisi.pdf', 'folder_id' => $revisi->id]);
        $admin = $this->isoAdmin();

        $this->actingAs($admin)->get(route('iso.procedures.index'))
            ->assertOk()->assertSee('Produksi')->assertSee('Di Root.pdf')->assertDontSee('Di Produksi.pdf')->assertDontSee('Buka folder Revisi');

        $this->actingAs($admin)->get(route('iso.procedures.index', ['folder' => $produksi->id]))
            ->assertOk()->assertSee('Revisi')->assertSee('Di Produksi.pdf')->assertDontSee('Di Root.pdf')->assertDontSee('Di Revisi.pdf');

        // Pencarian di dalam folder ikut mencari di subfolder-nya, tapi tidak di luar folder.
        $this->actingAs($admin)->get(route('iso.procedures.index', ['folder' => $produksi->id, 'q' => 'Di ']))
            ->assertOk()->assertSee('Di Produksi.pdf')->assertSee('Di Revisi.pdf')->assertDontSee('Di Root.pdf');

        // Breadcrumb: Procedures / Produksi / Revisi.
        $this->actingAs($admin)->get(route('iso.procedures.index', ['folder' => $revisi->id]))
            ->assertOk()->assertSeeInOrder(['Procedures', 'Produksi', 'Revisi']);
    }

    public function test_department_admin_cannot_fill_general_or_other_department_folders(): void
    {
        $deptAdmin = $this->deptAdmin($this->psd);
        $umum = $this->folder(null, ['nama' => 'Umum', 'kategori' => 'sop']);
        $pwd = $this->folder($this->pwd, ['nama' => 'Folder PWD', 'kategori' => 'sop']);
        $file = [UploadedFile::fake()->create('a.pdf', 5)];

        $this->actingAs($deptAdmin)->get(route('iso.sop.index', ['folder' => $umum->id]))->assertOk()->assertDontSee('+ Upload File');
        $this->actingAs($deptAdmin)->post(route('iso.sop.store'), ['files' => $file, 'folder_id' => $umum->id])->assertForbidden();
        $this->actingAs($deptAdmin)->post(route('iso.sop.folders.store'), ['nama' => 'Sub', 'parent_id' => $umum->id])->assertForbidden();
        $this->actingAs($deptAdmin)->post(route('iso.sop.store'), ['files' => $file, 'folder_id' => $pwd->id])
            ->assertSessionHasErrors(['folder_id' => 'Folder tidak ditemukan.']);
        $this->actingAs($deptAdmin)->get(route('iso.sop.index', ['folder' => $pwd->id]))->assertNotFound();
        $this->actingAs($deptAdmin)->delete(route('iso.sop.folders.destroy', $umum))->assertForbidden();

        $this->assertSame(0, IsoDocument::count());
        $this->assertSame(2, IsoFolder::count());
    }

    public function test_deleting_folder_removes_subfolders_and_files(): void
    {
        $produksi = $this->folder($this->psd, ['kategori' => 'sop']);
        $revisi = $this->folder($this->psd, ['parent_id' => $produksi->id, 'kategori' => 'sop']);
        $atas = $this->document($this->psd, ['folder_id' => $produksi->id, 'kategori' => 'sop']);
        $bawah = $this->document($this->psd, ['folder_id' => $revisi->id, 'kategori' => 'sop', 'path' => 'iso/sop/bawah.pdf']);
        $luar = $this->document($this->psd, ['kategori' => 'sop', 'path' => 'iso/sop/luar.pdf']);

        $this->actingAs($this->deptAdmin($this->psd))
            ->delete(route('iso.sop.folders.destroy', $produksi), ['keep' => ['folder' => (string) $produksi->id]])
            ->assertRedirect(route('iso.sop.index'));

        $this->assertSame(0, IsoFolder::count());
        $this->assertSame([$luar->id], IsoDocument::pluck('id')->all());
        Storage::disk('local')->assertMissing([$atas->path, $bawah->path]);
        Storage::disk('local')->assertExists($luar->path);
    }

    public function test_moving_file_into_folder_and_changing_folder_department(): void
    {
        $admin = $this->isoAdmin();
        $produksi = $this->folder($this->psd, ['nama' => 'Produksi']);
        $revisi = $this->folder($this->psd, ['nama' => 'Revisi', 'parent_id' => $produksi->id]);
        $document = $this->document(null, ['nama' => 'Manual.pdf']);

        $this->actingAs($admin)->put(route('iso.procedures.update', $document), ['nama' => 'Manual.pdf', 'folder_id' => $revisi->id])
            ->assertSessionHasNoErrors();
        $document->refresh();
        $this->assertSame($revisi->id, $document->folder_id);
        $this->assertSame($this->psd->id, $document->department_id);

        // Ganti departemen folder paling atas: semua subfolder & file ikut pindah.
        $this->actingAs($admin)->put(route('iso.procedures.folders.update', $produksi), ['nama' => 'Produksi', 'department_id' => $this->pwd->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->pwd->id, $revisi->refresh()->department_id);
        $this->assertSame($this->pwd->id, $document->refresh()->department_id);
    }

    public function test_image_card_gets_a_small_cached_thumbnail_that_is_removed_with_the_document(): void
    {
        $admin = $this->isoAdmin();
        $document = IsoDocument::factory()->create(['nama' => 'Foto.png', 'extension' => 'png', 'path' => 'iso/procedures/foto.png']);
        Storage::disk('local')->put($document->path, UploadedFile::fake()->image('foto.png', 1600, 1200)->getContent());

        $response = $this->actingAs($admin)->get(route('iso.procedures.thumbnail', $document))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        [$width, $height] = getimagesizefromstring($response->streamedContent());
        $this->assertSame([400, 300], [$width, $height]);
        $this->assertCount(1, Storage::disk('local')->files('iso/thumbs'));

        $this->actingAs($admin)->delete(route('iso.procedures.destroy', $document));
        $this->assertSame([], Storage::disk('local')->files('iso/thumbs'));
    }

    public function test_pdf_without_working_ghostscript_falls_back_to_icon_without_retrying(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $admin = $this->isoAdmin();
        $pdf = $this->document(null);

        $this->actingAs($admin)->get(route('iso.procedures.thumbnail', $pdf))->assertNotFound();
        $this->actingAs($admin)->get(route('iso.procedures.thumbnail', $pdf))->assertNotFound();

        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_thumbnail_command_prepares_thumbnails_and_can_retry_failures(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $image = IsoDocument::factory()->create(['extension' => 'png', 'path' => 'iso/procedures/foto.png']);
        Storage::disk('local')->put($image->path, UploadedFile::fake()->image('foto.png', 800, 600)->getContent());
        $this->document(null);

        $this->artisan('iso:thumbnails')->expectsOutputToContain('1 thumbnail siap, 1 gagal')->assertSuccessful();
        $this->artisan('iso:thumbnails')->assertSuccessful();
        Process::assertRanTimes(fn () => true, 1);

        $this->artisan('iso:thumbnails --ulang')->assertSuccessful();
        Process::assertRanTimes(fn () => true, 2);
    }

    public function test_thumbnail_follows_document_access(): void
    {
        $admin = $this->isoAdmin();
        $word = $this->document(null, ['nama' => 'Manual.docx', 'extension' => 'docx', 'path' => 'iso/procedures/manual.docx']);
        $milikPwd = $this->document($this->pwd, ['kategori' => 'sop']);

        $this->actingAs($admin)->get(route('iso.procedures.thumbnail', $word))->assertNotFound();
        $this->actingAs($this->deptAdmin($this->psd))->get(route('iso.sop.thumbnail', $milikPwd))->assertForbidden();
    }

    public function test_uploading_a_folder_recreates_its_structure_inside_the_open_folder(): void
    {
        $admin = $this->isoAdmin();
        $tujuan = $this->folder($this->psd, ['nama' => 'Tujuan']);

        $this->actingAs($admin)->postJson(route('iso.procedures.store'), [
            'folder_id' => $tujuan->id,
            'files' => [
                UploadedFile::fake()->create('Manual.pdf', 10),
                UploadedFile::fake()->create('Revisi 1.docx', 10),
                UploadedFile::fake()->create('Lepas.xlsx', 10),
            ],
            'folder_paths' => ['Produksi', 'Produksi/Revisi', ''],
            'folders' => ['Produksi/Kosong'],
        ])->assertOk()->assertJson(['uploaded' => 3, 'folders_created' => 3]);

        // Kiriman berikutnya (upload bertahap) masuk ke folder yang sudah dibuat, tidak membuat folder kembar.
        $this->actingAs($admin)->postJson(route('iso.procedures.store'), [
            'folder_id' => $tujuan->id,
            'files' => [UploadedFile::fake()->create('Revisi 2.docx', 10)],
            'folder_paths' => ['Produksi/Revisi'],
            'sudah_diupload' => 3,
            'folder_dibuat' => 3,
        ])->assertOk()->assertJson(['uploaded' => 1, 'folders_created' => 0, 'message' => '4 file & 3 folder berhasil diupload ke folder Tujuan.']);

        $produksi = IsoFolder::where('parent_id', $tujuan->id)->sole();
        $this->assertSame('Produksi', $produksi->nama);
        $this->assertEqualsCanonicalizing(['Revisi', 'Kosong'], IsoFolder::where('parent_id', $produksi->id)->pluck('nama')->all());
        $revisi = IsoFolder::where('parent_id', $produksi->id)->where('nama', 'Revisi')->sole();

        $this->assertSame(['Manual.pdf'], IsoDocument::where('folder_id', $produksi->id)->pluck('nama')->all());
        $this->assertEqualsCanonicalizing(['Revisi 1.docx', 'Revisi 2.docx'], IsoDocument::where('folder_id', $revisi->id)->pluck('nama')->all());
        $this->assertSame(['Lepas.xlsx'], IsoDocument::where('folder_id', $tujuan->id)->pluck('nama')->all());
        $this->assertSame([$this->psd->id], IsoFolder::pluck('department_id')->unique()->values()->all());
        $this->assertSame([$this->psd->id], IsoDocument::pluck('department_id')->unique()->values()->all());
    }

    public function test_uploaded_folder_never_merges_into_another_department_folder_with_the_same_name(): void
    {
        $milikPwd = $this->folder($this->pwd, ['nama' => 'Produksi', 'kategori' => 'sop']);

        $this->actingAs($this->deptAdmin($this->psd))->postJson(route('iso.sop.store'), [
            'files' => [UploadedFile::fake()->create('Manual.pdf', 10)],
            'folder_paths' => ['Produksi'],
        ])->assertOk();

        $this->assertSame(0, $milikPwd->documents()->count());
        $baru = IsoFolder::where('nama', 'Produksi')->where('department_id', $this->psd->id)->sole();
        $this->assertSame(1, $baru->documents()->count());
    }

    public function test_dragging_a_file_into_a_folder_and_back_out(): void
    {
        $admin = $this->isoAdmin();
        $produksi = $this->folder($this->psd, ['nama' => 'Produksi']);
        $document = $this->document($this->pwd, ['nama' => 'Manual.pdf']);

        $this->actingAs($admin)->patchJson(route('iso.procedures.move'), ['documents' => [$document->id], 'folder_id' => $produksi->id])
            ->assertOk()->assertJson(['message' => 'Manual.pdf dipindahkan ke folder Produksi.']);
        $document->refresh();
        $this->assertSame($produksi->id, $document->folder_id);
        $this->assertSame($this->psd->id, $document->department_id);

        // Keluar dari folder: departemen terakhir dipertahankan.
        $this->actingAs($admin)->patchJson(route('iso.procedures.move'), ['documents' => [$document->id], 'folder_id' => null])->assertOk();
        $document->refresh();
        $this->assertNull($document->folder_id);
        $this->assertSame($this->psd->id, $document->department_id);
    }

    public function test_list_view_choice_is_remembered_per_menu(): void
    {
        $admin = $this->isoAdmin();
        $this->document(null, ['kategori' => 'procedures']);
        $this->document(null, ['kategori' => 'sop']);

        $this->actingAs($admin)->withUnencryptedCookie('iso_view', 'procedures')->get(route('iso.procedures.index'))
            ->assertOk()->assertSee('class="doc-grid is-list"', false);
        $this->actingAs($admin)->withUnencryptedCookie('iso_view', 'procedures')->get(route('iso.sop.index'))
            ->assertOk()->assertSee('class="doc-grid"', false)->assertDontSee('doc-grid is-list', false);
    }

    public function test_several_selected_files_are_moved_together(): void
    {
        $produksi = $this->folder($this->psd, ['nama' => 'Produksi', 'kategori' => 'sop']);
        $documents = collect([$this->document($this->psd, ['kategori' => 'sop']), $this->document(null, ['kategori' => 'sop']), $this->document($this->pwd, ['kategori' => 'sop'])]);

        $this->actingAs($this->isoAdmin())->patchJson(route('iso.sop.move'), ['documents' => $documents->pluck('id')->all(), 'folder_id' => $produksi->id])
            ->assertOk()->assertJson(['message' => '3 file dipindahkan ke folder Produksi.']);

        $this->assertSame([$produksi->id], IsoDocument::pluck('folder_id')->unique()->values()->all());
        $this->assertSame([$this->psd->id], IsoDocument::pluck('department_id')->unique()->values()->all());
    }

    public function test_bulk_move_is_refused_entirely_when_one_file_is_not_manageable(): void
    {
        $produksi = $this->folder($this->psd, ['kategori' => 'sop']);
        $milikSendiri = $this->document($this->psd, ['kategori' => 'sop']);
        $umum = $this->document(null, ['kategori' => 'sop']);

        $this->actingAs($this->deptAdmin($this->psd))
            ->patchJson(route('iso.sop.move'), ['documents' => [$milikSendiri->id, $umum->id], 'folder_id' => $produksi->id])
            ->assertForbidden();

        $this->assertNull($milikSendiri->refresh()->folder_id);
        $this->assertNull($umum->refresh()->folder_id);
    }

    public function test_department_admin_cannot_drag_files_into_other_department_folders(): void
    {
        $folderPwd = $this->folder($this->pwd, ['kategori' => 'sop']);
        $document = $this->document($this->psd, ['kategori' => 'sop']);

        $this->actingAs($this->deptAdmin($this->psd))->patchJson(route('iso.sop.move'), ['documents' => [$document->id], 'folder_id' => $folderPwd->id])
            ->assertStatus(422)->assertJsonValidationErrors('folder_id');

        $this->assertNull($document->refresh()->folder_id);
    }
}

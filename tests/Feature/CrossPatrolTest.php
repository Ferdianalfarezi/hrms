<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Sms\Models\CrossPatrol;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CrossPatrolTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    private Department $pwd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
        $this->pwd = Department::create(['name' => 'PWD']);
    }

    private function deptAdmin(Department $department): User
    {
        $user = User::factory()->create();
        $user->assignRole('sms', 'dept_admin', $department->id);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'tanggal' => '2026-09-24',
            'plant' => 'Plant 1',
            'zona' => '3',
            'finding' => 'Kabel AC menjuntai',
            'foto' => UploadedFile::fake()->image('temuan.jpg', 400, 300),
            'efek' => 'Tersetrum',
            'resiko' => 'B',
            'area' => 'OBEYA',
            'rekomendasi' => 'Kabel ac di rapikan',
            'target' => '2026-09-30',
            'pic' => 'Yayan S.',
            'kategori' => 'electrical',
            'patroler' => 'Yayan R & Miftari',
            ...$overrides,
        ];
    }

    private function patrol(Department $department, array $overrides = []): CrossPatrol
    {
        return CrossPatrol::create([
            'tanggal' => '2026-09-24', 'plant' => 'Plant 1', 'zona' => 3, 'finding' => 'Temuan '.$department->name, 'foto' => 'cross-patrol/a.jpg',
            'efek' => '2R', 'resiko' => 'C', 'area' => 'OBEYA', 'department_id' => $department->id, 'rekomendasi' => 'Perbaiki',
            'target' => '2026-09-30', 'pic' => 'Yayan S.', 'kategori' => '5r', 'patroler' => 'Yayan R & Miftari', 'status' => 'open',
            ...$overrides,
        ]);
    }

    public function test_menu_and_list_only_show_own_department(): void
    {
        $this->patrol($this->psd);
        $this->patrol($this->pwd);

        $this->actingAs($this->deptAdmin($this->psd))->get('/sms/cross-patrols')
            ->assertOk()
            ->assertSee('Cross Patrol')
            ->assertSee('Temuan PSD')
            ->assertDontSee('Temuan PWD');
    }

    public function test_create_is_open_then_close_needs_repair_photo(): void
    {
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->post('/sms/cross-patrols', $this->payload())->assertSessionHasNoErrors();
        $patrol = CrossPatrol::sole();
        $this->assertSame('open', $patrol->status);
        $this->assertSame($this->psd->id, $patrol->department_id);

        $this->actingAs($admin)->put("/sms/cross-patrols/{$patrol->id}", $this->payload(['foto' => null, 'status' => 'closed']))
            ->assertSessionHasErrors('foto_perbaikan');
        $this->assertSame('open', $patrol->fresh()->status);

        $this->actingAs($admin)->put("/sms/cross-patrols/{$patrol->id}", $this->payload([
            'foto' => null, 'status' => 'closed', 'foto_perbaikan' => UploadedFile::fake()->image('fix.jpg'),
        ]))->assertSessionHasNoErrors();
        $this->assertSame('closed', $patrol->fresh()->status);
        $this->assertNotNull($patrol->fresh()->closed_at);
    }

    public function test_cannot_edit_other_department(): void
    {
        $patrol = $this->patrol($this->pwd);

        $this->actingAs($this->deptAdmin($this->psd))
            ->put("/sms/cross-patrols/{$patrol->id}", $this->payload(['foto' => null, 'status' => 'open']))
            ->assertForbidden();
    }

    public function test_filters_by_tanggal_and_plant(): void
    {
        $this->patrol($this->psd, ['finding' => 'Temuan Plant 1']);
        $this->patrol($this->psd, ['finding' => 'Temuan Plant 2', 'plant' => 'Plant 2']);
        $this->patrol($this->psd, ['finding' => 'Temuan Kemarin', 'tanggal' => '2026-09-23']);

        $this->actingAs($this->deptAdmin($this->psd))->get('/sms/cross-patrols?tanggal=2026-09-24&plant=Plant+1')
            ->assertSee('Temuan Plant 1')
            ->assertDontSee('Temuan Plant 2')
            ->assertDontSee('Temuan Kemarin');
    }

    public function test_zona_is_required_one_to_nine_and_filterable(): void
    {
        $admin = $this->deptAdmin($this->psd);

        $this->actingAs($admin)->post('/sms/cross-patrols', $this->payload(['zona' => null]))->assertSessionHasErrors('zona');
        $this->actingAs($admin)->post('/sms/cross-patrols', $this->payload(['zona' => '10']))->assertSessionHasErrors('zona');
        $this->actingAs($admin)->post('/sms/cross-patrols', $this->payload(['zona' => '9', 'finding' => 'Temuan Zona Sembilan']))->assertSessionHasNoErrors();
        $this->assertSame(9, CrossPatrol::sole()->zona);

        $this->patrol($this->psd, ['finding' => 'Temuan Zona Lima', 'zona' => 5]);
        $this->actingAs($admin)->get('/sms/cross-patrols?zona=5')
            ->assertSee('Temuan Zona Lima')
            ->assertDontSee('Temuan Zona Sembilan');
    }

    public function test_print_selected_cards_only_from_own_department(): void
    {
        $a = $this->patrol($this->psd, ['finding' => 'Temuan Satu']);
        $b = $this->patrol($this->psd, ['finding' => 'Temuan Dua', 'status' => 'closed', 'target' => '2026-10-02']);
        $this->patrol($this->psd, ['finding' => 'Tidak dicentang']);
        $other = $this->patrol($this->pwd, ['finding' => 'Punya departemen lain']);

        $response = $this->actingAs($this->deptAdmin($this->psd))
            ->get('/sms/cross-patrols/print?'.http_build_query(['ids' => [$a->id, $b->id, $other->id]]))
            ->assertOk()
            ->assertSee('Temuan Satu')
            ->assertSee('Temuan Dua')
            ->assertSee('02.10.2026')
            ->assertDontSee('Tidak dicentang')
            ->assertDontSee('Punya departemen lain');

        $this->assertSame(2, substr_count($response->getContent(), 'class="card"'));
        // Status aktif ditandai sel abu-abu: Temuan Satu = OPEN, Temuan Dua = CLOSE.
        $this->assertSame(1, substr_count($response->getContent(), '<div class="opt on">OPEN</div>'));
        $this->assertSame(1, substr_count($response->getContent(), '<div class="opt on">CLOSE</div>'));
    }

    public function test_print_without_selection_uses_filters_and_paginates_twenty_five_per_sheet(): void
    {
        foreach (range(1, 26) as $i) {
            $this->patrol($this->psd, ['finding' => "Temuan Zona5 {$i}", 'zona' => 5]);
        }
        $this->patrol($this->psd, ['finding' => 'Temuan zona lain', 'zona' => 2]);

        $response = $this->actingAs($this->deptAdmin($this->psd))->get('/sms/cross-patrols/print?zona=5')
            ->assertOk()
            ->assertDontSee('Temuan zona lain');

        $this->assertSame(26, substr_count($response->getContent(), 'class="card"'));
        $this->assertSame(2, substr_count($response->getContent(), 'class="sheet"'));
        $this->assertStringContainsString('<span class="num">26</span>', $response->getContent());
    }

    public function test_export_excel_follows_form_layout(): void
    {
        $fake = UploadedFile::fake()->image('a.jpg', 400, 300);
        Storage::disk('public')->putFileAs('cross-patrol', $fake, 'a.jpg');
        Storage::disk('public')->putFileAs('cross-patrol', $fake, 'b.jpg');

        $this->patrol($this->psd, ['finding' => 'Tembok obeya bolong']);
        $this->patrol($this->psd, ['finding' => 'Kabel AC menjuntai', 'kategori' => 'electrical', 'resiko' => 'B',
            'foto_perbaikan' => 'cross-patrol/b.jpg', 'status' => 'closed']);
        $this->patrol($this->pwd, ['finding' => 'Punya departemen lain']);

        $response = $this->actingAs($this->deptAdmin($this->psd))->get('/sms/cross-patrols/export?tanggal=2026-09-24');
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('FORM TEMUAN SAFETY PATROL', $sheet->getCell('C1')->getValue());
        $this->assertStringContainsString('Plant 1', $sheet->getCell('A2')->getValue()); // plant
        $this->assertStringContainsString('ZONA', $sheet->getCell('A3')->getValue());
        $this->assertStringContainsString('Zona 3', $sheet->getCell('A3')->getValue());
        $this->assertStringContainsString('24 September 2026', $sheet->getCell('A4')->getValue());
        $this->assertSame(': K3 / FMR / 15', $sheet->getCell('N1')->getValue());
        $this->assertSame('FINDING', $sheet->getCell('B5')->getValue());
        $this->assertSame('STATUS', $sheet->getCell('M5')->getValue());

        // Baris data mulai baris 7, hanya departemen sendiri.
        $this->assertSame('Tembok obeya bolong', $sheet->getCell('B7')->getValue());
        $this->assertSame('Kabel AC menjuntai', $sheet->getCell('B8')->getValue());
        $this->assertNull($sheet->getCell('B9')->getValue());
        $this->assertSame('Stop 6 (Electrical)', $sheet->getCell('K8')->getValue());
        $this->assertSame('5R', $sheet->getCell('K7')->getValue());
        $this->assertSame('30.09.2026', $sheet->getCell('H7')->getValue());
        $this->assertSame('Ringan', $sheet->getCell('E7')->getValue()); // kode C
        $this->assertSame('Sedang', $sheet->getCell('E8')->getValue()); // kode B

        // Status: open = kolom OPEN kuning, closed = kolom CLOSED kuning.
        $this->assertSame('FFFF00', $sheet->getStyle('M7')->getFill()->getStartColor()->getRGB());
        $this->assertSame('FFFF00', $sheet->getStyle('N8')->getFill()->getStartColor()->getRGB());
        $this->assertSame('none', $sheet->getStyle('M8')->getFill()->getFillType());

        // Foto temuan 2 + foto perbaikan 1.
        $this->assertCount(3, $sheet->getDrawingCollection());
    }
}

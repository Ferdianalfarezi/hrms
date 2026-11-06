<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Iso\Mail\IsoDocumentUpdated;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Models\IsoFolder;
use App\Modules\Iso\Models\IsoRecipient;
use Database\Seeders\ApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsoNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $psd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(ApplicationSeeder::class);
        $this->psd = Department::create(['name' => 'PSD']);
    }

    private function isoAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('iso', 'admin');

        return $user;
    }

    private function deptAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('iso', 'dept_admin', $this->psd->id);

        return $user;
    }

    private function document(array $overrides = []): IsoDocument
    {
        $document = IsoDocument::factory()->create(['department_id' => $this->psd->id, 'nama' => 'SOP Mesin.pdf', 'kategori' => 'sop', ...$overrides]);
        Storage::disk('local')->put($document->path, 'isi file');

        return $document;
    }

    public function test_email_menu_is_for_iso_admin_only(): void
    {
        $this->actingAs($this->deptAdmin())->get(route('iso.emails.index'))->assertForbidden();
        $this->actingAs($this->deptAdmin())->post(route('iso.emails.store'), ['nama' => 'Budi', 'email' => 'budi@contoh.com'])->assertForbidden();

        $this->actingAs($this->isoAdmin())->get(route('iso.emails.index'))->assertOk()->assertSee('Email Notifikasi');
    }

    public function test_iso_admin_manages_recipients(): void
    {
        $admin = $this->isoAdmin();

        $this->actingAs($admin)->post(route('iso.emails.store'), ['nama' => 'Budi QA', 'email' => ' Budi@Contoh.com '])
            ->assertRedirect(route('iso.emails.index'));
        $recipient = IsoRecipient::sole();
        $this->assertSame('budi@contoh.com', $recipient->email);
        $this->assertTrue($recipient->is_active);

        $this->actingAs($admin)->post(route('iso.emails.store'), ['nama' => 'Dobel', 'email' => 'BUDI@contoh.com'])
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar.']);

        $this->actingAs($admin)->patchJson(route('iso.emails.status', $recipient), ['is_active' => false])->assertOk()->assertJson(['is_active' => false]);
        $this->assertFalse($recipient->fresh()->is_active);

        $this->actingAs($admin)->put(route('iso.emails.update', $recipient), ['nama' => 'Budi', 'email' => 'budi@contoh.com'])->assertSessionHasNoErrors();
        $this->assertSame('Budi', $recipient->fresh()->nama);

        $this->actingAs($admin)->delete(route('iso.emails.destroy', $recipient))->assertRedirect(route('iso.emails.index'));
        $this->assertModelMissing($recipient);
    }

    public function test_notification_goes_only_to_selected_active_recipients(): void
    {
        Mail::fake();
        $document = $this->document();
        $budi = IsoRecipient::factory()->create(['email' => 'budi@contoh.com']);
        $sari = IsoRecipient::factory()->create(['email' => 'sari@contoh.com']);
        $nonaktif = IsoRecipient::factory()->inactive()->create(['email' => 'lama@contoh.com']);
        IsoRecipient::factory()->create(['email' => 'tidak.dipilih@contoh.com']);
        $admin = $this->isoAdmin();

        $this->actingAs($admin)->post(route('iso.sop.notify', $document), [
            'recipients' => [$budi->id, $sari->id, $nonaktif->id],
            'pesan' => 'Revisi langkah 4.',
        ])->assertRedirect(route('iso.sop.index'))->assertSessionHas('status', 'Notifikasi SOP Mesin.pdf terkirim ke 2 email.');

        Mail::assertSentCount(2);
        Mail::assertSent(IsoDocumentUpdated::class, fn (IsoDocumentUpdated $mail) => $mail->hasTo('budi@contoh.com') && $mail->pesan === 'Revisi langkah 4.');
        Mail::assertSent(IsoDocumentUpdated::class, fn (IsoDocumentUpdated $mail) => $mail->hasTo('sari@contoh.com'));
        Mail::assertNotSent(IsoDocumentUpdated::class, fn (IsoDocumentUpdated $mail) => $mail->hasTo('lama@contoh.com'));

        $document->refresh();
        $this->assertNotNull($document->notified_at);
        $this->assertSame($admin->id, $document->notified_by);
    }

    public function test_inactive_document_cannot_be_notified(): void
    {
        Mail::fake();
        $document = $this->document(['is_active' => false]);
        $recipient = IsoRecipient::factory()->create();

        $this->actingAs($this->isoAdmin())->post(route('iso.sop.notify', $document), ['recipients' => [$recipient->id]])
            ->assertSessionHasErrors(['notify' => 'Notifikasi hanya bisa dikirim untuk dokumen yang aktif.']);

        Mail::assertNothingSent();
    }

    public function test_department_admin_cannot_send_notification(): void
    {
        Mail::fake();
        $document = $this->document();
        $recipient = IsoRecipient::factory()->create();

        $this->actingAs($this->deptAdmin())->post(route('iso.sop.notify', $document), ['recipients' => [$recipient->id]])->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_only_inactive_recipients_selected_is_rejected(): void
    {
        Mail::fake();
        $document = $this->document();
        $nonaktif = IsoRecipient::factory()->inactive()->create();

        $this->actingAs($this->isoAdmin())->post(route('iso.sop.notify', $document), ['recipients' => [$nonaktif->id]])
            ->assertSessionHasErrors(['recipients' => 'Tidak ada penerima aktif yang dipilih.']);

        Mail::assertNothingSent();
    }

    public function test_email_content_and_optional_attachment(): void
    {
        $folder = IsoFolder::factory()->create(['kategori' => 'sop', 'nama' => 'SOP1', 'department_id' => $this->psd->id]);
        $document = $this->document(['folder_id' => $folder->id]);
        $sender = $this->isoAdmin();

        $mail = new IsoDocumentUpdated($document, $sender, 'Revisi langkah 4.', lampirkan: true);
        $mail->assertHasSubject('Pembaruan Dokumen ISO: SOP Mesin.pdf');
        $mail->assertFrom('iso.admin@step.co.id', 'ISO Admin');
        $mail->assertSeeInHtml('SOP Mesin.pdf');
        $mail->assertSeeInHtml('SOP1');
        $mail->assertSeeInHtml('PSD');
        $mail->assertSeeInHtml('Revisi langkah 4.');
        $mail->assertSeeInHtml(route('iso.sop.file', $document));
        $this->assertCount(1, $mail->attachments());
        $this->assertSame('SOP Mesin.pdf', $mail->attachments()[0]->as);

        $tanpaLampiran = new IsoDocumentUpdated($document, $sender);
        $tanpaLampiran->assertDontSeeInHtml('Catatan:');
        $this->assertSame([], $tanpaLampiran->attachments());
    }
}

<?php

namespace App\Modules\Iso\Mail;

use App\Models\User;
use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifikasi pembaruan dokumen ISO ke email penerima (menu Email). Dikirim langsung (tidak di-queue)
 * supaya Admin ISO langsung tahu berapa email yang berhasil terkirim.
 */
class IsoDocumentUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public IsoDocument $document,
        public User $sender,
        public ?string $pesan = null,
        public bool $lampirkan = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.iso_from.address'), config('mail.iso_from.name')),
            subject: 'Pembaruan Dokumen ISO: '.$this->document->nama,
        );
    }

    public function content(): Content
    {
        $folder = $this->document->folder?->pathFromRoot()->pluck('nama')->implode(' / ');

        return new Content(
            markdown: 'mail.iso.document-updated',
            with: [
                'kategori' => IsoDocument::KATEGORI[$this->document->kategori] ?? $this->document->kategori,
                'folder' => $folder ?: '-',
                'departemen' => $this->document->department?->name ?? 'Umum',
                // Link membuka file lewat aplikasi (perlu login sebagai Admin ISO / Admin Departemen).
                'url' => route("iso.{$this->document->kategori}.file", $this->document),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->lampirkan
            ? [Attachment::fromStorageDisk('local', $this->document->path)->as($this->document->nama)]
            : [];
    }
}

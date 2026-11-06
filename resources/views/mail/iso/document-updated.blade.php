<x-mail::message>
# Pembaruan Dokumen ISO

Ada pembaruan pada dokumen ISO berikut:

<x-mail::table>
| | |
|:--|:--|
| **Dokumen** | {{ $document->nama }} |
| **Menu** | {{ $kategori }} |
| **Folder** | {{ $folder }} |
| **Departemen** | {{ $departemen }} |
| **Diperbarui** | {{ $document->updated_at->translatedFormat('d F Y H:i') }} |
| **Dikirim oleh** | {{ $sender->name }} |
</x-mail::table>

@if ($pesan)
**Catatan:**

{{ $pesan }}
@endif

@if ($lampirkan)
File dokumen terlampir di email ini.
@endif

<x-mail::button :url="$url">
Buka Dokumen
</x-mail::button>

Untuk membuka dokumen lewat tombol di atas, login ke {{ config('app.name') }} terlebih dahulu.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>

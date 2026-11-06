<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Iso\Models\IsoRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Menu Email: daftar penerima notifikasi pembaruan dokumen ISO. Khusus Admin ISO.
 */
class RecipientController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'iso');

        $search = trim((string) $request->query('q'));

        $recipients = IsoRecipient::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('nama')
            ->paginate(50)
            ->withQueryString();

        return view('iso.emails.index', [
            'recipients' => $recipients,
            'search' => $search,
            'activeCount' => IsoRecipient::active()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-module', 'iso');

        $data = $this->validated($request);
        $recipient = IsoRecipient::create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('iso.emails.index')->with('status', "Email {$recipient->email} berhasil ditambahkan.");
    }

    public function update(Request $request, IsoRecipient $recipient): RedirectResponse
    {
        Gate::authorize('manage-module', 'iso');

        $recipient->update([...$this->validated($request, $recipient), 'updated_by' => $request->user()->id]);

        return redirect()->route('iso.emails.index', array_filter(['q' => $request->input('keep.q')]))
            ->with('status', "Email {$recipient->email} berhasil diperbarui.");
    }

    public function destroy(IsoRecipient $recipient): RedirectResponse
    {
        Gate::authorize('manage-module', 'iso');

        $recipient->delete();

        return redirect()->route('iso.emails.index')->with('status', "Email {$recipient->email} berhasil dihapus.");
    }

    /**
     * Aktif / nonaktifkan penerima tanpa reload halaman. Penerima nonaktif tidak dikirimi notifikasi.
     */
    public function status(Request $request, IsoRecipient $recipient): JsonResponse
    {
        Gate::authorize('manage-module', 'iso');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $recipient->update(['is_active' => (bool) $data['is_active'], 'updated_by' => $request->user()->id]);

        return response()->json(['is_active' => $recipient->is_active]);
    }

    /**
     * @return array{nama: string, email: string}
     */
    private function validated(Request $request, ?IsoRecipient $recipient = null): array
    {
        // Email disimpan huruf kecil supaya "Budi@X.com" dan "budi@x.com" dianggap sama.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('iso_recipients', 'email')->ignore($recipient)],
        ], [
            'nama.required' => 'Nama penerima wajib diisi.',
            'nama.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email ini sudah terdaftar.',
        ]);

        return ['nama' => Str::squish($data['nama']), 'email' => $data['email']];
    }
}

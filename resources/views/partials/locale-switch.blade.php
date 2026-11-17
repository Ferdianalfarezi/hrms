{{-- Pilihan bahasa tampilan (ID / JP). Muat ulang penuh (tanpa Turbo) supaya teks di script ikut berganti. --}}
<div class="lang-switch" role="group" aria-label="{{ __('Bahasa') }}">
    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
        <a href="{{ route('locale.switch', $code) }}" data-turbo="false" lang="{{ $code }}"
           @class(['active' => app()->getLocale() === $code]) @if (app()->getLocale() === $code) aria-current="true" @endif
           title="{{ $code === 'ja' ? '日本語' : 'Bahasa Indonesia' }}">{{ $label }}</a>
    @endforeach
</div>

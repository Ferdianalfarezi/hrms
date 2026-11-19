{{-- Pilihan bahasa tampilan (ID / JP). Muat ulang penuh (tanpa Turbo) supaya teks di script ikut berganti. --}}
<div class="lang-switch" role="group" aria-label="{{ __('Bahasa') }}">
    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
        <a href="{{ route('locale.switch', $code) }}" data-turbo="false" lang="{{ $code }}"
           @class(['active' => app()->getLocale() === $code]) @if (app()->getLocale() === $code) aria-current="true" @endif
           title="{{ $code === 'ja' ? '日本語' : 'Bahasa Indonesia' }}">{{ $label }}
            {{-- Bendera SVG (emoji bendera tidak tampil di Windows). --}}
            <svg class="flag" viewBox="0 0 30 20" aria-hidden="true">
                @if ($code === 'ja')
                    <rect width="30" height="20" fill="#fff"/><circle cx="15" cy="10" r="6" fill="#bc002d"/>
                @else
                    <rect width="30" height="10" fill="#e70011"/><rect y="10" width="30" height="10" fill="#fff"/>
                @endif
            </svg>
        </a>
    @endforeach
</div>

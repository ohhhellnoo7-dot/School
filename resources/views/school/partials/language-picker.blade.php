@php($languageNames = ['en' => 'English', 'km' => 'ខ្មែរ', 'zh' => '中文'])
<details class="theme-switcher language-switcher" data-language-menu>
    <summary aria-label="{{ __('Choose language') }}"><img src="{{ asset('images/flags/'.(array_key_exists(app()->getLocale(), $languageNames) ? app()->getLocale() : 'en').'.svg') }}" width="24" height="16" alt="" aria-hidden="true"><span lang="{{ app()->getLocale() }}">{{ $languageNames[app()->getLocale()] ?? 'English' }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
    <form action="{{ route('language.update') }}" method="post" class="language-options" aria-label="{{ __('Language') }}">
        @csrf
        @foreach($languageNames as $locale => $label)
        <button type="submit" name="locale" value="{{ $locale }}" class="language-option" data-language-choice aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}"><span class="d-inline-flex align-items-center gap-2"><img src="{{ asset('images/flags/'.$locale.'.svg') }}" width="24" height="16" alt="" aria-hidden="true"><span lang="{{ $locale }}">{{ $label }}</span></span><i class="bi bi-check2" aria-hidden="true"></i></button>
        @endforeach
    </form>
</details>

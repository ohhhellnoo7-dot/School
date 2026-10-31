@php($languageNames = ['en' => 'English', 'km' => 'ខ្មែរ', 'zh' => '中文'])
@php($languageFlags = ['en' => '🇬🇧', 'km' => '🇰🇭', 'zh' => '🇨🇳'])
<details class="theme-switcher language-switcher" data-language-menu>
    <summary aria-label="{{ __('Choose language') }}"><span aria-hidden="true">{{ $languageFlags[app()->getLocale()] ?? $languageFlags['en'] }}</span><span lang="{{ app()->getLocale() }}">{{ $languageNames[app()->getLocale()] ?? 'English' }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
    <form action="{{ route('language.update') }}" method="post" class="language-options" aria-label="{{ __('Language') }}">
        @csrf
        @foreach($languageNames as $locale => $label)
        <button type="submit" name="locale" value="{{ $locale }}" class="language-option" data-language-choice aria-pressed="{{ app()->getLocale() === $locale ? 'true' : 'false' }}"><span class="d-inline-flex align-items-center gap-2"><span aria-hidden="true">{{ $languageFlags[$locale] }}</span><span lang="{{ $locale }}">{{ $label }}</span></span><i class="bi bi-check2" aria-hidden="true"></i></button>
        @endforeach
    </form>
</details>

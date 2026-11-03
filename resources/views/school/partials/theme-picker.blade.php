<details class="theme-switcher" data-theme-menu>
    <summary aria-label="{{ __('Choose color theme') }}"><span class="theme-preview" aria-hidden="true"></span><span data-current-theme>{{ __('Light') }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
    <div class="theme-options" role="group" aria-label="{{ __('Color theme') }}">
        <div class="theme-options-heading"><strong>{{ __('Make yourself at home') }}</strong><small>{{ __('Preview on hover. Click to keep your favorite.') }}</small></div>
        <label class="theme-search-label" for="theme-search">{{ __('Find a theme') }}</label>
        <input id="theme-search" type="search" class="form-control theme-search" data-theme-search placeholder="{{ __('Search 20 themes...') }}" autocomplete="off">
        @foreach(collect(config('scholar-themes'))->groupBy('group', true) as $group => $themes)
        <section class="theme-group" data-theme-group aria-label="{{ __($group) }}">
            <h3>{{ __($group) }}</h3>
            <div class="theme-group-options">
            @foreach($themes as $value => $theme)
                <button type="button" class="theme-option" data-theme-choice="{{ $value }}" aria-pressed="false">
                    <span class="theme-swatch swatch-{{ $value }}" aria-hidden="true"></span><span><strong>{{ __($theme['name']) }}</strong><small>{{ $theme['dark'] ? __('Dark palette') : __('Light palette') }}</small></span><i class="bi bi-check2" aria-hidden="true"></i>
                </button>
            @endforeach
            </div>
        </section>
        @endforeach
        <p class="theme-search-empty" data-theme-empty hidden role="status">{{ __('No matching themes.') }}</p>
    </div>
</details>
<noscript><p class="small text-secondary">{{ __('Enable JavaScript to change the workspace theme.') }}</p></noscript>

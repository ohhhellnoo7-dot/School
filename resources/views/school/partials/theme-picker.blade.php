<details class="theme-switcher" data-theme-menu>
    <summary aria-label="{{ __('Choose color theme') }}"><span class="theme-preview" aria-hidden="true"></span><span data-current-theme>{{ __('Light') }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
    <div class="theme-options" role="group" aria-label="{{ __('Color theme') }}">
        <div class="theme-options-heading"><strong>{{ __('Make yourself at home') }}</strong><small>{{ __('Choose your workspace theme') }}</small></div>
        @foreach(['light' => ['Light', 'Clean & professional'], 'night' => ['Night', 'A softer after-hours glow'], 'relax' => ['Relax', 'A little room to breathe'], 'cute' => ['Cute', 'A playful pastel touch'], 'ocean' => ['Ocean', 'Fresh coastal blues'], 'sunset' => ['Sunset', 'A warm welcome'], 'luxury' => ['Luxury', 'Charcoal & champagne'], 'galaxy' => ['Galaxy', 'A new perspective']] as $value => [$label, $description])
        <button type="button" class="theme-option" data-theme-choice="{{ $value }}" aria-pressed="false">
            <span class="theme-swatch swatch-{{ $value }}" aria-hidden="true"></span><span><strong>{{ __($label) }}</strong><small>{{ __($description) }}</small></span><i class="bi bi-check2" aria-hidden="true"></i>
        </button>
        @endforeach
    </div>
</details>
<noscript><p class="small text-secondary">{{ __('Enable JavaScript to change the workspace theme.') }}</p></noscript>

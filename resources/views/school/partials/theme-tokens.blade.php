{{-- New palettes feed the existing shared component aliases; no page data is changed. --}}
<style id="scholar-theme-palettes">
@foreach(config('scholar-themes') as $id => $theme)
@if(isset($theme['palette']))
@php([$page, $sidebar, $primary, $accent, $surface, $text, $muted, $action] = $theme['palette'])
:root[data-theme="{{ $id }}"] {
    color-scheme: {{ $theme['dark'] ? 'dark' : 'light' }};
    --app-bg:{{ $page }}; --surface:{{ $surface }}; --surface-raised:{{ $surface }};
    --text-primary:{{ $text }}; --text-secondary:{{ $muted }};
    --primary:{{ $primary }}; --accent:{{ $accent }}; --action:{{ $action }};
    --on-action:{{ $theme['dark'] ? $page : '#FFFFFF' }};
    --sidebar-bg:{{ $sidebar }}; --sidebar-text:#EDF3FA; --sidebar-heading:#FFFFFF;
    --sidebar-active:{{ $primary }};
    --surface-tint:color-mix(in srgb,{{ $primary }} 10%,{{ $surface }});
    --border-color:color-mix(in srgb,{{ $text }} 18%,{{ $surface }});
    --shadow-color:color-mix(in srgb,{{ $sidebar }} 12%,transparent);
    --hero-veil:{{ $page }}; --hero-opacity:.95;
    --auth-shade:{{ $sidebar }}; --auth-tint:{{ $primary }}; --auth-highlight:{{ $accent }};
    --auth-button-start:{{ $action }}; --auth-button-end:{{ $action }};
    --card-radius:{{ $theme['radius'] }}px; --control-radius:{{ min($theme['radius'], 14) }}px;
    --success:{{ $theme['dark'] ? '#7DE1B3' : '#236A49' }};
    --warning:{{ $theme['dark'] ? '#F3CD83' : '#835B12' }};
    --danger:{{ $theme['dark'] ? '#FFA3B7' : '#B3344F' }};
    --violet:{{ $theme['dark'] ? '#C9B0FF' : '#7051A3' }};
    --theme-nav-start:color-mix(in srgb,{{ $sidebar }} 88%,{{ $accent }});
    --theme-nav-end:{{ $sidebar }}; --theme-nav-text:#EDF3FA;
    --theme-nav-muted:color-mix(in srgb,{{ $accent }} 35%,#FFFFFF);
    --theme-nav-active:{{ $action }}; --theme-nav-active-ink:{{ $theme['dark'] ? $page : '#FFFFFF' }};
    --theme-tile-1:{{ $primary }}; --theme-tile-2:{{ $accent }};
    --theme-tile-3:color-mix(in srgb,{{ $primary }} 55%,{{ $accent }});
    --theme-tile-4:color-mix(in srgb,{{ $primary }} 25%,{{ $accent }});
}
.swatch-{{ $id }} { background:linear-gradient(135deg,{{ $sidebar }} 50%,{{ $accent }} 50%); }
@endif
@endforeach
</style>

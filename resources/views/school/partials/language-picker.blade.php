<form action="{{ route('language.update') }}" method="post" class="language-picker d-flex align-items-center gap-2">
    @csrf
    <label for="language" class="visually-hidden">{{ __('Language') }}</label>
    <select id="language" name="locale" class="form-select" aria-label="{{ __('Language') }}" onchange="this.form.submit()">
        <option value="en" @selected(app()->getLocale() === 'en')>English</option>
        <option value="km" @selected(app()->getLocale() === 'km')>ខ្មែរ</option>
        <option value="zh" @selected(app()->getLocale() === 'zh')>中文</option>
    </select>
    <noscript><button class="btn btn-outline-secondary" type="submit">OK</button></noscript>
</form>

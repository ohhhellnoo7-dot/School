<fieldset data-class-day-picker class="mb-3">
    <legend class="form-label">Class days</legend>
    <div class="btn-group" role="group" aria-label="Class days">
        @foreach(['weekday' => 'Weekday', 'weekend' => 'Weekend'] as $value => $label)
            <input type="radio" class="btn-check" name="class_days" id="{{ $prefix }}-{{ $value }}" value="{{ $value }}" @checked(old('class_days', 'weekday') === $value)>
            <label class="btn btn-outline-primary" for="{{ $prefix }}-{{ $value }}">{{ $label }}</label>
        @endforeach
    </div>
    @error('class_days')<p class="text-danger">{{ $message }}</p>@enderror
</fieldset>

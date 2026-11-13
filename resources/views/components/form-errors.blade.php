@props(['targets' => []])

@if ($errors->any())
    <div class="hf-callout hf-callout--danger hf-error-summary" role="alert" tabindex="-1" data-hf-error-summary>
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <div>
            <strong>Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}</strong>
            <ul>
                @foreach ($errors->keys() as $field)
                    <li><a href="#{{ $targets[$field] ?? $field }}">{{ $errors->first($field) }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

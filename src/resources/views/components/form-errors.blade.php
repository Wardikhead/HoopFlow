{{-- Blade view for Form errors. --}}
@props(['targets' => []])

@if ($errors->any())
    <div class="hf-callout hf-callout--danger hf-error-summary" role="alert" tabindex="-1" data-hf-error-summary>
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <div>
            <strong>Please fix {{ count($errors->keys()) }} {{ \Illuminate\Support\Str::plural('field', count($errors->keys())) }}</strong>
            <ul>
                @foreach ($errors->keys() as $field)
                    <li><a href="#{{ $targets[$field] ?? $field }}">{{ $errors->first($field) }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

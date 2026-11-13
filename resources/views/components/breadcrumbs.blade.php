@props(['items' => []])

@if (count($items))
    <nav class="hf-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($items as $item)
                <li>
                    @if (! $loop->last && ! empty($item['href']))
                        <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif

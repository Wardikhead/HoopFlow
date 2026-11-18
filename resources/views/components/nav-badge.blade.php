@props(['count' => 0, 'dot' => false])

@if ($dot)
    <span class="hf-nav-badge hf-nav-badge--dot" aria-hidden="true"></span>
@elseif ($count > 0)
    <span class="hf-nav-badge" aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span>
@endif

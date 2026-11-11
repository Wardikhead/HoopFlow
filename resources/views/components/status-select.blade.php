@props([
    'value',
    'options',
    'action',
    'playerId',
    'testid',
    'label' => 'Status',
])

@php
    $current = collect($options)->firstWhere('value', $value) ?? $options[0];
    $tone = $current['tone'] ?? 'slate';
@endphp

<div
    {{ $attributes->class(['hf-status-select']) }}
    data-testid="{{ $testid }}"
    x-data="{
        open: false,
        position: { left: 0, top: 0, width: 0 },
        toggle() {
            if (this.open) {
                this.open = false;
                return;
            }
            this.place();
            this.open = true;
            this.$nextTick(() => this.place());
        },
        place() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const width = Math.max(rect.width, 252);
            const menuHeight = this.$refs.menu ? this.$refs.menu.offsetHeight : 190;
            this.position.width = width;
            this.position.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
            const below = rect.bottom + 6;
            this.position.top = below + menuHeight > window.innerHeight - 8
                ? Math.max(8, rect.top - menuHeight - 6)
                : below;
        },
        close(focus) {
            this.open = false;
            if (focus) {
                this.$refs.trigger.focus();
            }
        },
        focusFirst() {
            this.$nextTick(() => this.$refs.menu.querySelector('[role=option]')?.focus());
        },
        focusLast() {
            this.$nextTick(() => {
                const items = this.$refs.menu.querySelectorAll('[role=option]');
                items[items.length - 1]?.focus();
            });
        },
        move(delta) {
            const items = Array.from(this.$refs.menu.querySelectorAll('[role=option]'));
            if (! items.length) {
                return;
            }
            const index = items.indexOf(document.activeElement);
            const next = index === -1
                ? (delta > 0 ? 0 : items.length - 1)
                : (index + delta + items.length) % items.length;
            items[next].focus();
        }
    }"
    x-on:keydown.escape.stop.prevent="close(true)"
    x-on:click.outside="open = false"
    x-on:scroll.document.capture="open = false"
    x-on:resize.window="open = false"
>
    <button
        type="button"
        x-ref="trigger"
        class="hf-status-select__trigger hf-status-select__trigger--{{ $tone }}"
        aria-haspopup="listbox"
        aria-label="{{ $label }}: {{ $current['label'] }}"
        x-bind:aria-expanded="open"
        x-on:click="toggle()"
        x-on:keydown.arrow-down.prevent="open = true; focusFirst()"
        x-on:keydown.arrow-up.prevent="open = true; focusLast()"
    >
        <i class="bi bi-{{ $current['icon'] ?? 'circle' }}" aria-hidden="true"></i>
        <span class="hf-status-select__value">{{ $current['label'] }}</span>
        <i class="bi bi-chevron-down hf-status-select__caret" aria-hidden="true"></i>
    </button>

    <div
        x-ref="menu"
        class="hf-status-select__menu"
        role="listbox"
        aria-label="{{ $label }}"
        x-cloak
        x-show="open"
        x-bind:style="'left: ' + position.left + 'px; top: ' + position.top + 'px; width: ' + position.width + 'px;'"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.home.prevent="focusFirst()"
        x-on:keydown.end.prevent="focusLast()"
        x-on:keydown.tab="open = false"
    >
        @foreach ($options as $option)
            @php $selected = $option['value'] === $value; @endphp
            <button
                type="button"
                role="option"
                aria-selected="{{ $selected ? 'true' : 'false' }}"
                class="hf-status-select__option hf-status-select__option--{{ $option['tone'] ?? 'slate' }}{{ $selected ? ' is-selected' : '' }}"
                wire:click="{{ $action }}({{ $playerId }}, '{{ $option['value'] }}')"
                x-on:click="open = false"
            >
                <span class="hf-status-select__icon"><i class="bi bi-{{ $option['icon'] ?? 'circle' }}" aria-hidden="true"></i></span>
                <span class="hf-status-select__copy">
                    <span class="hf-status-select__label">{{ $option['label'] }}</span>
                    <span class="hf-status-select__hint">{{ $option['description'] }}</span>
                </span>
                <i class="bi bi-check2 hf-status-select__check" aria-hidden="true"></i>
            </button>
        @endforeach
    </div>
</div>

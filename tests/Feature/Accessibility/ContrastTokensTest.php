<?php

/**
 * Guards the colour decisions that carry WCAG 2.1 AA contrast.
 *
 * The test reads the same SCSS the browser gets, so a token or rule change
 * that drops below the required ratio fails here before it ships.
 */
function a11yBlock(string $scss, string $selector): string
{
    $pattern = '/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s';

    expect(preg_match($pattern, $scss, $matches))->toBe(1, "Missing {$selector} block in _tokens.scss");

    return $matches[1];
}

/**
 * A11y token.
 *
 * @param  string  $block  The block.
 * @param  string  $name  The name.
 * @return string The result.
 */
function a11yToken(string $block, string $name): string
{
    expect(preg_match('/--'.preg_quote($name, '/').':\s*([^;]+);/', $block, $matches))
        ->toBe(1, "Missing --{$name} token");

    return trim($matches[1]);
}

/**
 * @return array{0: float, 1: float, 2: float}
 */
function a11yRgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');

    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $rgb
 */
function a11yLuminance(array $rgb): float
{
    $channels = array_map(
        fn (float $channel) => $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4,
        $rgb,
    );

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $foreground
 * @param  array{0: float, 1: float, 2: float}  $background
 */
function a11yContrast(array $foreground, array $background): float
{
    $light = max(a11yLuminance($foreground), a11yLuminance($background));
    $dark = min(a11yLuminance($foreground), a11yLuminance($background));

    return ($light + 0.05) / ($dark + 0.05);
}

/**
 * Composite a foreground colour at an alpha over an opaque background.
 *
 * @param  array{0: float, 1: float, 2: float}  $foreground
 * @param  array{0: float, 1: float, 2: float}  $background
 * @return array{0: float, 1: float, 2: float}
 */
function a11yBlend(array $foreground, float $alpha, array $background): array
{
    return [
        $foreground[0] * $alpha + $background[0] * (1 - $alpha),
        $foreground[1] * $alpha + $background[1] * (1 - $alpha),
        $foreground[2] * $alpha + $background[2] * (1 - $alpha),
    ];
}

/**
 * Read the alpha out of a `color-mix(in srgb, var(--hf-x) NN%, transparent)` token.
 */
function a11yMixAlpha(string $value): float
{
    expect(preg_match('/var\(--hf-[a-z-]+\)\s+(\d+)%/', $value, $matches))
        ->toBe(1, "Cannot read a colour-mix percentage from: {$value}");

    return (int) $matches[1] / 100;
}

/**
 * Pull a declaration out of a single CSS rule.
 */
function a11yRule(string $scss, string $selector): string
{
    expect(preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $scss, $matches))
        ->toBe(1, "Missing {$selector} rule");

    return $matches[1];
}

beforeEach(function () {
    $this->tokens = file_get_contents(base_path('resources/css/hf/_tokens.scss'));
    $this->components = file_get_contents(base_path('resources/css/hf/_components.scss'));
    $this->data = file_get_contents(base_path('resources/css/hf/_data.scss'));
    $this->surfaces = file_get_contents(base_path('resources/css/hf/_surfaces.scss'));

    $this->light = a11yBlock($this->tokens, ':root');
    $this->dark = a11yBlock($this->tokens, "[data-theme='dark']");
});

it('keeps the on-dark eyebrow readable over the matchday gradient', function () {
    $rule = a11yRule($this->surfaces, '.hf-eyebrow--on-dark');
    expect(preg_match('/(?<![-\w])color:\s*var\(--(hf-[a-z-]+)\)/', $rule, $matches))->toBe(1);
    $token = $matches[1];

    foreach ([
        'light' => [$this->light, 'hf-primary'],
        'dark' => [$this->dark, 'hf-primary'],
    ] as $theme => [$block, $primaryToken]) {
        $rail = a11yRgb(a11yToken($block, 'hf-rail'));
        $primary = a11yRgb(a11yToken($block, $primaryToken));
        $text = a11yRgb(a11yToken($block, $token));
        $worstCase = a11yBlend($primary, 0.28, $rail);

        expect(a11yContrast($text, $worstCase))
            ->toBeGreaterThanOrEqual(4.5, "Eyebrow ({$token}) fails on the {$theme} matchday gradient");
    }
});

it('keeps the primary chip readable on the dark matchday strip', function () {
    $rule = a11yRule($this->surfaces, '.hf-matchday .hf-chip--primary');
    expect(preg_match('/(?<![-\w])color:\s*var\(--(hf-[a-z-]+)\)/', $rule, $matches))->toBe(1);
    $token = $matches[1];

    foreach ([
        'light' => [$this->light, 'hf-primary'],
        'dark' => [$this->dark, 'hf-primary'],
    ] as $theme => [$block, $primaryToken]) {
        $rail = a11yRgb(a11yToken($block, 'hf-rail'));
        $primary = a11yRgb(a11yToken($block, $primaryToken));
        $text = a11yRgb(a11yToken($block, $token));
        $chipBackground = a11yBlend($primary, 0.28, $rail);

        expect(a11yContrast($text, $chipBackground))
            ->toBeGreaterThanOrEqual(4.5, "Chip ({$token}) fails on the {$theme} matchday strip");
    }
});

it('keeps status select warning and danger text at AA on their tinted backgrounds', function () {
    foreach (['warning', 'danger'] as $tone) {
        $rule = a11yRule($this->components, ".hf-status-select__trigger--{$tone}");
        expect(preg_match('/(?<![-\w])color:\s*var\(--(hf-[a-z-]+)\)/', $rule, $textMatches))->toBe(1);
        expect(preg_match('/background:\s*color-mix\(in srgb,\s*var\(--(hf-[a-z-]+)\)\s+(\d+)%/', $rule, $bgMatches))->toBe(1);

        foreach (['light' => $this->light, 'dark' => $this->dark] as $theme => $block) {
            $surface = a11yRgb(a11yToken($block, 'hf-surface-2'));
            $bgTone = a11yRgb(a11yToken($block, $bgMatches[1]));
            $text = a11yRgb(a11yToken($block, $textMatches[1]));
            $background = a11yBlend($bgTone, (int) $bgMatches[2] / 100, $surface);

            expect(a11yContrast($text, $background))
                ->toBeGreaterThanOrEqual(4.5, "{$tone} status text ({$textMatches[1]}) fails on the {$theme} theme");
        }
    }
});

it('keeps the landing primary button readable in both themes', function () {
    $base = file_get_contents(base_path('resources/css/landing/_base.scss'));
    $rule = a11yRule($base, '.hf-lp .hf-btn--primary');

    expect(preg_match('/(?<![-\w])color:\s*(#[0-9a-fA-F]{6})/', $rule, $textMatch))
        ->toBe(1, 'Landing primary button must set an explicit text colour, not inherit --hf-on-action');
    expect(preg_match('/(?<![-\w])background:\s*(#[0-9a-fA-F]{6})/', $rule, $backgroundMatch))
        ->toBe(1);

    $hover = a11yRule($base, '.hf-lp .hf-btn--primary:hover');
    expect(preg_match('/(?<![-\w])background:\s*(#[0-9a-fA-F]{6})/', $hover, $hoverMatch))->toBe(1);

    foreach ([$backgroundMatch[1], $hoverMatch[1]] as $background) {
        expect(a11yContrast(a11yRgb($textMatch[1]), a11yRgb($background)))
            ->toBeGreaterThanOrEqual(4.5, "Landing primary button text {$textMatch[1]} fails on {$background}");
    }
});

it('keeps pressed filter and period controls readable', function () {
    foreach ([
        'filter' => [$this->data, '.hf-filter.is-active'],
        'period nav' => [$this->data, '.hf-period-nav__btn.is-active'],
    ] as $name => [$scss, $selector]) {
        $rule = a11yRule($scss, $selector);

        expect(preg_match('/(?<![-\w])color:\s*var\(--(hf-[a-z-]+)\)/', $rule, $textMatch))
            ->toBe(1, "{$name} must use an on-colour token instead of a hardcoded white");
        expect(preg_match('/(?<![-\w])background:\s*var\(--(hf-[a-z-]+)\)/', $rule, $backgroundMatch))
            ->toBe(1, "{$name} must use the action token as its background");

        foreach (['light' => $this->light, 'dark' => $this->dark] as $theme => $block) {
            $text = a11yRgb(a11yToken($block, $textMatch[1]));
            $background = a11yRgb(a11yToken($block, $backgroundMatch[1]));

            expect(a11yContrast($text, $background))
                ->toBeGreaterThanOrEqual(4.5, "{$name} text ({$textMatch[1]}) fails on the {$theme} theme");
        }
    }
});

it('keeps the danger button readable in both themes', function () {
    $rule = a11yRule($this->components, '.hf-btn--danger');

    expect(preg_match('/(?<![-\w])color:\s*var\(--(hf-[a-z-]+)\)/', $rule, $textMatch))
        ->toBe(1, 'Danger button must use an on-danger token instead of a hardcoded white');
    expect(preg_match('/(?<![-\w])background:\s*var\(--(hf-[a-z-]+)\)/', $rule, $backgroundMatch))
        ->toBe(1);

    foreach (['light' => $this->light, 'dark' => $this->dark] as $theme => $block) {
        $text = a11yRgb(a11yToken($block, $textMatch[1]));
        $background = a11yRgb(a11yToken($block, $backgroundMatch[1]));

        expect(a11yContrast($text, $background))
            ->toBeGreaterThanOrEqual(4.5, "Danger button text ({$textMatch[1]}) fails on the {$theme} theme");
    }
});

it('keeps the input boundary at 3:1 against the field surface', function () {
    foreach (['light' => $this->light, 'dark' => $this->dark] as $theme => $block) {
        $ink = a11yRgb(a11yToken($block, 'hf-ink'));
        $surface = a11yRgb(a11yToken($block, 'hf-surface-2'));
        $alpha = a11yMixAlpha(a11yToken($block, 'hf-border-strong'));
        $border = a11yBlend($ink, $alpha, $surface);

        expect(a11yContrast($border, $surface))
            ->toBeGreaterThanOrEqual(3.0, "Input border fails non-text contrast on the {$theme} theme");
    }
});

it('keeps the focus ring at 3:1 against the field surface', function () {
    $focus = a11yToken($this->light, 'hf-focus');
    preg_match('/var\(--(hf-[a-z-]+)\)/', $focus, $matches);
    expect($matches[1] ?? null)->not->toBeNull("Focus token does not reference a colour: {$focus}");

    $alpha = preg_match('/var\(--hf-[a-z-]+\)\s+(\d+)%/', $focus, $mix) ? (int) $mix[1] / 100 : 1.0;

    foreach (['light' => $this->light, 'dark' => $this->dark] as $theme => $block) {
        $surface = a11yRgb(a11yToken($block, 'hf-surface-2'));
        $ring = a11yBlend(a11yRgb(a11yToken($block, $matches[1])), $alpha, $surface);

        expect(a11yContrast($ring, $surface))
            ->toBeGreaterThanOrEqual(3.0, "Focus ring fails non-text contrast on the {$theme} theme");
    }
});

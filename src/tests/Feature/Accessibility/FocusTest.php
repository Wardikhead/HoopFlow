<?php

/**
 * Guards keyboard-focus visibility against the sticky workspace chrome.
 */
function focusRule(string $scss, string $selector): string
{
    expect(preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $scss, $matches))
        ->toBe(1, "Missing {$selector} rule");

    return $matches[1];
}

it('keeps keyboard focus clear of the sticky workspace topbar', function () {
    $base = file_get_contents(base_path('resources/css/hf/_base.scss'));
    $shell = file_get_contents(base_path('resources/css/hf/_shell.scss'));

    $topbar = focusRule($shell, '.hf-topbar');
    expect($topbar)->toContain('position: sticky');
    expect(preg_match('/min-height:\s*(\d+)px/', $topbar, $heightMatch))->toBe(1);
    $topbarHeight = (int) $heightMatch[1];

    expect(preg_match('/html:has\(body:not\(\.hf-lp\)\)\s*\{([^}]*)\}/', $base, $blockMatch))
        ->toBe(1, 'The workspace must scope its scroll offset away from the landing page');
    expect(preg_match('/scroll-padding-top:\s*(\d+)px/', $blockMatch[1], $paddingMatch))
        ->toBe(1, 'The workspace must set scroll-padding-top so the sticky topbar cannot hide focused content');
    expect((int) $paddingMatch[1])->toBeGreaterThanOrEqual($topbarHeight);
});

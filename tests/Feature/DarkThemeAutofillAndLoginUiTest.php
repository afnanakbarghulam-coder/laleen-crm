<?php

namespace Tests\Feature;

use Tests\TestCase;

class DarkThemeAutofillAndLoginUiTest extends TestCase
{
    public function test_login_page_loads_dark_autofill_theme_and_visible_svg_password_toggle(): void
    {
        $res = $this->get('/login')->assertOk();

        $res->assertSee('css/luxury-theme.css');
        $res->assertSee('luxe-password-toggle');
        $res->assertSee('js-auth-password-toggle');
        $res->assertSee('aria-label="Show password"', false);
        $res->assertSee('autocomplete="current-password"', false);
        $res->assertDontSee('bx bx-hide');

        $luxuryCss = file_get_contents(public_path('css/luxury-theme.css'));
        $this->assertNotFalse($luxuryCss);
        $this->assertStringContainsString('color-scheme: dark;', $luxuryCss);
        $this->assertStringContainsString('input:-webkit-autofill', $luxuryCss);
        $this->assertStringContainsString('textarea:-webkit-autofill', $luxuryCss);
        $this->assertStringContainsString('select:-webkit-autofill', $luxuryCss);
        $this->assertStringContainsString('input:autofill', $luxuryCss);
        $this->assertStringContainsString('-webkit-text-fill-color: var(--luxe-autofill-text, #FCF8F8) !important;', $luxuryCss);
        $this->assertStringContainsString('0 0 0 1000px var(--luxe-input-bg-autofill, #1c1614) inset !important;', $luxuryCss);
        $this->assertStringContainsString('transition: background-color 99999s ease-in-out 0s', $luxuryCss);

        $appCss = file_get_contents(resource_path('css/app.css'));
        $this->assertNotFalse($appCss);
        $this->assertStringContainsString('color-scheme: dark;', $appCss);
        $this->assertStringContainsString('input:-webkit-autofill', $appCss);
        $this->assertStringContainsString('input:autofill', $appCss);
        $this->assertStringContainsString('-webkit-text-fill-color: #FCF8F8 !important;', $appCss);
    }
}

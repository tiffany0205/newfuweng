<?php

namespace Tests\Feature;

use Tests\TestCase;

class DicePresentationTest extends TestCase
{
    public function test_idle_dice_uses_an_independent_tilted_3d_presentation(): void
    {
        $blade = file_get_contents(resource_path('views/game/index.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('class="dice-float-shell"', $blade);
        $this->assertMatchesRegularExpression(
            '/<div class="dice-float-shell">.*?<\/div>\s*<\/div>\s*<span class="dice-shadow"><\/span>\s*<\/div>\s*<span class="dice-prompt">/s',
            $blade
        );

        $this->assertSame(6, substr_count($css, '.dice-cube.face-'));
        foreach (range(1, 6) as $face) {
            $this->assertMatchesRegularExpression(
                '/\.dice-cube\.face-'.$face.'\{transform:rotateZ\(-2deg\) rotateX\(-18deg\) rotateY\(30deg\)/',
                $css
            );
        }

        $this->assertStringContainsString('@keyframes diceIdleBreathe', $css);
        $this->assertStringContainsString('@keyframes diceShadowBreathe', $css);
        $this->assertStringContainsString('.center-action.is-rolling .dice-float-shell{animation:none', $css);
        $this->assertStringContainsString('.dice-float-shell,.dice-shadow{animation:none!important', $css);
        $this->assertStringNotContainsString('animation:diceFloat', $css);
    }
}

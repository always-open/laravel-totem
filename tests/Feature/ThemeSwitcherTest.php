<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;
use Studio\Totem\Totem;

class ThemeSwitcherTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function pages(): array
    {
        return [
            'tasks list' => ['totem.tasks.all', false],
            'task create' => ['totem.task.create', false],
            'task view' => ['totem.task.view', true],
            'task edit' => ['totem.task.edit', true],
            'upcoming' => ['totem.upcoming', false],
        ];
    }

    private function page(string $routeName, bool $needsTask): TestResponse
    {
        $this->signIn();

        return $this->get(route($routeName, $needsTask ? ['totemTask' => Task::factory()->create()] : []))
            ->assertStatus(200);
    }

    #[DataProvider('pages')]
    public function test_sidebar_renders_the_three_theme_options(string $routeName, bool $needsTask)
    {
        $this->page($routeName, $needsTask)->assertSeeInOrder([
            '<aside',
            'class="totem-theme-switcher',
            'data-totem-theme="light"',
            'Light',
            'data-totem-theme="dark"',
            'Dark',
            'data-totem-theme="system"',
            'Browser default',
            '</aside>',
        ], false);
    }

    #[DataProvider('pages')]
    public function test_theme_script_is_inlined_in_head_before_the_first_stylesheet(string $routeName, bool $needsTask)
    {
        $source = Totem::themeScript();
        $this->assertStringContainsString('data-totem-theme', $source);

        $html = $this->page($routeName, $needsTask)->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $scriptAt = strpos($head, '<script>'.$source.'</script>');
        $firstStylesheetAt = strpos($head, 'rel="stylesheet"');

        $this->assertIsInt($scriptAt, 'The theme script is not inlined as a classic <script> in <head>.');
        $this->assertIsInt($firstStylesheetAt);
        $this->assertLessThan($firstStylesheetAt, $scriptAt);
    }

    public function test_dark_stylesheet_loads_after_the_package_stylesheets()
    {
        $html = $this->page('totem.tasks.all', false)->getContent();

        $this->assertMatchesRegularExpression(
            '#/vendor/totem/css/app\.css.*/vendor/totem/css/components\.css.*/vendor/totem/css/dark\.css#s',
            substr($html, 0, (int) strpos($html, '</head>')),
        );
    }

    public function test_dark_stylesheet_is_published_with_the_assets()
    {
        $this->assertFileExists(public_path('vendor/totem/css/dark.css'));
        $this->assertFileEquals(__DIR__.'/../../public/css/dark.css', public_path('vendor/totem/css/dark.css'));
    }

    public function test_storage_key_defaults_to_totem_theme()
    {
        $html = $this->page('totem.tasks.all', false)->getContent();

        $this->assertStringContainsString('<html lang="en" data-totem-theme-key="totem-theme">', $html);
    }

    public function test_storage_key_follows_config()
    {
        config(['totem.web.theme_storage_key' => 'theme']);

        $html = $this->page('totem.tasks.all', false)->getContent();

        $this->assertStringContainsString('<html lang="en" data-totem-theme-key="theme">', $html);
    }

    public function test_storage_key_is_escaped()
    {
        config(['totem.web.theme_storage_key' => '"><script>alert(1)</script>']);

        $html = $this->page('totem.tasks.all', false)->getContent();

        $this->assertStringContainsString('data-totem-theme-key="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
    }
}

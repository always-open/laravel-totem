<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Result;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class ViewTaskTest extends TestCase
{
    public function test_user_can_view_task()
    {
        $this->signIn();
        $task = Task::factory()->create();
        $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
        $response->assertStatus(200);
        $response->assertSee($task->description);
        $response->assertSee('Studio\Totem\Console\Commands\ListSchedule');
        $response->assertSee($task->expression);
    }

    public function test_guest_can_not_view_task()
    {
        $task = Task::factory()->create();
        $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
        $response->assertStatus(403);
    }

    /**
     * Regression test for v12.0.1's empty-output-popup bug.
     *
     * Before v12.0.2, resources/views/tasks/view.blade.php used @json() to
     * bind the task result into the <task-output :output="..."> attribute.
     * @json() emits raw JSON whose outer `"` delimiters collide with the
     * attribute's own `"` wrappers, making the HTML malformed. Browsers
     * parse `:output=""hello""` as `:output=""` (empty) + orphan text, so
     * Vue receives an empty string and the modal's <pre> renders blank.
     *
     * Fix: use Js::from(), which for string input emits a bare single-quoted
     * JS string literal (e.g. `'hello "world"'`). This is safe inside
     * double-quoted HTML attributes because the inner `"` characters are
     * unicode-escaped to `"` via JSON_HEX_QUOT.
     *
     * This test renders the task-view page with a result whose output
     * contains every character class known to break attribute encoding,
     * parses the response with DOMDocument, extracts the `:output`
     * attribute value, decodes it, and asserts round-trip equality with
     * the source string.
     *
     * Negative control: reverting the Blade change to `@json(...)` MUST
     * cause this test to fail. Verify manually during PR review.
     *
     * Excluded from the corpus:
     * - null bytes (`\0`) — the task_results.result column is a UTF-8
     *   text column that does not accept them; they are unreachable in
     *   production data.
     */
    public function test_output_attribute_roundtrips_edge_cases()
    {
        $corpus = implode("\n", [
            'double "quotes" inside a line',
            'backslash \\ and forward slash /',
            'less-than <script>alert(1)</script> tags',
            'apostrophe \'s and ampersand & symbols',
            'emoji 🔥 and four-byte UTF-8 𝕏',
            "CR\r LF newline above, tab\there, and nothing fancy",
        ]);

        $this->signIn();

        $task = Task::factory()->create();
        Result::factory()->create([
            'task_id' => $task->id,
            'result' => $corpus,
            'duration' => 1234,
            'ran_at' => now(),
        ]);

        $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
        $response->assertStatus(200);

        $decoded = $this->extractTaskOutputAttribute($response->getContent());

        $this->assertSame(
            $corpus,
            $decoded,
            'Round-tripped :output attribute must exactly equal the source result string.'
        );
    }

    /**
     * Extract the first <task-output> element's :output attribute from the
     * response HTML and decode it back to the original string.
     *
     * The attribute value is a JS expression. With the Js::from() fix, the
     * attribute value is a bare single-quoted JS string literal (e.g.
     * `'hello "world"'`) — Laravel's `Js.php:86-88` short path for scalar
     * string inputs. The legacy `@json()` output was the raw JSON literal;
     * HTML attribute parsing truncates that because its outer `"` delimiters
     * collide with the attribute's own `"` wrappers.
     */
    private function extractTaskOutputAttribute(string $html): string
    {
        $dom = new \DOMDocument();
        // libxml is strict about our custom elements; suppress warnings.
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $nodes = $dom->getElementsByTagName('task-output');
        $this->assertGreaterThan(
            0,
            $nodes->length,
            '<task-output> element must be present in the task-view response.'
        );

        /** @var \DOMElement $node */
        $node = $nodes->item(0);
        $attr = $node->getAttribute(':output');
        $this->assertNotSame(
            '',
            $attr,
            ':output attribute must not be empty — empty means the Blade binding broke again.'
        );

        // Two shapes we accept:
        //   1. Js::from() form (the fix): a single-quoted JS string literal,
        //      e.g. `'hello "world"'`. All special characters are
        //      unicode-escaped (`"` for `"`, `'` for `'`, etc.) or
        //      standard JSON-compatible escapes (`\\`, `\/`, `\r`, `\n`, `\t`),
        //      so stripping the outer `'`s and wrapping in `"..."` yields
        //      valid JSON we can json_decode.
        //   2. Legacy @json() form (the bug): the raw JSON literal `"hello"`.
        //      HTML attribute parsing truncates it; the assertNotSame above
        //      catches that case, but we keep json_decode as a defensive path.
        if (preg_match("/^'(.*)'$/s", $attr, $m)) {
            $inner = $m[1];
            $json = json_decode('"'.$inner.'"', true, 512, JSON_THROW_ON_ERROR);

            return (string) $json;
        }

        // Defensive fallback for the legacy @json() shape. Unreachable in practice:
        // if the fix is reverted, the assertNotSame('', $attr, …) guard above fires
        // first because HTML parsing truncates the attribute to an empty string.
        // Retained as documentation of what the broken shape would produce if some
        // future change kept the attribute non-empty but still wrong.
        $json = json_decode($attr, true);

        return (string) $json;
    }
}

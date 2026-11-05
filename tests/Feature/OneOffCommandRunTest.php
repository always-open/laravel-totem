<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Studio\Totem\OneOffCommandRun;
use Studio\Totem\OneOffCommandRunner;
use Studio\Totem\Task;
use Studio\Totem\Tests\Fixtures\CountingCommand;
use Studio\Totem\Tests\TestCase;
use Studio\Totem\Tests\TestUser;
use Studio\Totem\Totem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TypeError;

/**
 * Totem's "Run once" page runs one allowlisted Artisan command with an arguments string inside
 * the web request, records the run in `one_off_command_runs`, and lists past runs newest first.
 *
 * The fixture commands are registered on the console kernel per test and allowlisted through
 * `totem.artisan.command_filter`, the config Totem::getCommands() reads; `totem-test:forbidden`
 * is registered but never allowlisted. Each fixture counts its own handle() calls, so "runs
 * nothing" and "runs once" are read from the command object the kernel invoked.
 *
 * Routes are requested by path under `totem.web.route_prefix`, so a missing or misnamed route
 * fails on the response rather than on route().
 */
class OneOffCommandRunTest extends TestCase
{
    private CountingCommand $echo;

    private CountingCommand $org;

    private CountingCommand $required;

    private CountingCommand $throws;

    private CountingCommand $throwsError;

    private CountingCommand $confirms;

    private CountingCommand $binary;

    private CountingCommand $throwsMarkup;

    private CountingCommand $throwsBinary;

    private CountingCommand $orgHex;

    private CountingCommand $forbidden;

    private TestUser $operator;

    public function setUp(): void
    {
        parent::setUp();

        $this->echo = $this->echoCommand();
        $this->org = $this->orgCommand();
        $this->required = $this->requiredValueCommand();
        $this->throws = $this->throwingCommand();
        $this->throwsError = $this->errorThrowingCommand();
        $this->confirms = $this->confirmingCommand();
        $this->binary = $this->binaryOutputCommand();
        $this->throwsMarkup = $this->markupThrowingCommand();
        $this->throwsBinary = $this->binaryThrowingCommand();
        $this->orgHex = $this->orgHexCommand();
        $this->forbidden = $this->forbiddenCommand();

        $kernel = $this->app->make(Kernel::class);
        foreach ([$this->echo, $this->org, $this->required, $this->throws, $this->throwsError, $this->confirms, $this->binary, $this->throwsMarkup, $this->throwsBinary, $this->orgHex, $this->forbidden] as $command) {
            $kernel->registerCommand($command);
        }

        config(['totem.artisan.command_filter' => [
            'totem-test:echo',
            'totem-test:org',
            'totem-test:required',
            'totem-test:throws',
            'totem-test:throws-error',
            'totem-test:confirms',
            'totem-test:binary',
            'totem-test:throws-markup',
            'totem-test:throws-binary',
            'totem-test:org-hex',
        ]]);

        $this->operator = TestUser::factory()->create(['email' => 'operator@example.com']);

        // Totem's gate admits the operator only, so a signed-in user it rejects can be tested.
        $operatorId = $this->operator->getKey();
        Totem::auth(fn ($request) => $request->user()?->getKey() === $operatorId);
    }

    // ---------------------------------------------------------------------
    // Fixture commands
    // ---------------------------------------------------------------------

    /**
     * Prints its argument and option through info/error/line, sleeps 30 ms so the run has a
     * measurable duration, and exits with --exit.
     */
    private function echoCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:echo {target} {--org=} {--exit=0}';

            public function handle(): int
            {
                $this->runs++;
                usleep(30_000);

                $this->info('info: target='.$this->argument('target'));
                $this->error('error: target='.$this->argument('target'));
                $this->line('org='.var_export($this->option('org'), true));

                return (int) $this->option('exit');
            }
        };
    }

    /** Reports what `--org` (an optional-value option) reached it as. */
    private function orgCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:org {--org=}';

            public function handle(): int
            {
                $this->runs++;
                $this->line('org='.var_export($this->option('org'), true));

                return self::SUCCESS;
            }
        };
    }

    /** Declares `--org` as an option that requires a value. */
    private function requiredValueCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:required';

            public function __construct()
            {
                parent::__construct();

                $this->addOption('org', null, InputOption::VALUE_REQUIRED);
            }

            public function handle(): int
            {
                $this->runs++;
                $this->line('handled with org='.var_export($this->option('org'), true));

                return self::SUCCESS;
            }
        };
    }

    /** Writes one line, then throws. */
    private function throwingCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:throws';

            public function handle(): int
            {
                $this->runs++;
                $this->line('before the throw');

                throw new RuntimeException('fixture exploded');
            }
        };
    }

    /** Writes one line, then throws a TypeError, an \Error that is not an \Exception. */
    private function errorThrowingCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:throws-error';

            public function handle(): int
            {
                $this->runs++;
                $this->line('before the error');

                throw new TypeError('boom-type-error');
            }
        };
    }

    /** Reports whether its input is interactive, then asks a yes/no question defaulting to no. */
    private function confirmingCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:confirms {rest?*}';

            public function handle(): int
            {
                $this->runs++;
                $this->line('interactive='.var_export($this->input->isInteractive(), true));

                if ($this->confirm('Proceed?', false)) {
                    $this->line('confirmed');
                } else {
                    $this->line('declined');
                }

                return self::SUCCESS;
            }
        };
    }

    /**
     * Writes a line holding a NUL byte and a line holding an invalid UTF-8 sequence. The NUL line
     * is written raw: Symfony's output formatter turns a NUL into a backslash.
     */
    private function binaryOutputCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:binary';

            public function handle(): int
            {
                $this->runs++;
                $this->output->writeln("before\0after", OutputInterface::OUTPUT_RAW);
                $this->line("bad \xC3\x28 utf8");

                return self::SUCCESS;
            }
        };
    }

    /** Throws an exception whose message holds a NUL byte and an invalid UTF-8 sequence. */
    private function binaryThrowingCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:throws-binary';

            public function handle(): int
            {
                $this->runs++;

                throw new RuntimeException("x\0y bad \xC3\x28 z");
            }
        };
    }

    /** Prints the raw bytes `--org` reached it as, hex-encoded. */
    private function orgHexCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:org-hex {--org=}';

            public function handle(): int
            {
                $this->runs++;
                $this->line('org-hex='.bin2hex((string) $this->option('org')));

                return self::SUCCESS;
            }
        };
    }

    /** Throws an exception whose message carries HTML markup. */
    private function markupThrowingCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:throws-markup';

            public function handle(): int
            {
                $this->runs++;

                throw new RuntimeException('<b>boom</b>');
            }
        };
    }

    /** Registered on the kernel and kept out of the allowlist. */
    private function forbiddenCommand(): CountingCommand
    {
        return new class extends CountingCommand
        {
            protected $signature = 'totem-test:forbidden';

            public function handle(): int
            {
                $this->runs++;

                return self::SUCCESS;
            }
        };
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function runsPath(string $suffix = ''): string
    {
        return '/'.config('totem.web.route_prefix').'/one-off-runs'.$suffix;
    }

    /**
     * A run row in the shape the runner writes, inserted directly so list/detail tests do not
     * depend on the runner.
     */
    private function insertRun(array $overrides = []): int
    {
        return DB::table('one_off_command_runs')->insertGetId(array_merge([
            'command' => 'totem-test:echo',
            'arguments' => 'seeded',
            'user_id' => null,
            'user_email' => 'seeded-operator@example.com',
            'started_at' => Carbon::parse('2026-10-01 12:00:00'),
            'duration_ms' => 41,
            'exit_code' => 0,
            'output' => "seeded output\n",
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Totem's main panel only, so two renders of one page compare without the parts of the
     * layout that do not belong to the run.
     */
    private function mainPanel(string $html): string
    {
        $open = '<section class="uk-width-5-6@l">';
        $start = strpos($html, $open);
        $this->assertNotFalse($start);

        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start + strlen('</section>'));
    }

    // ---------------------------------------------------------------------
    // Routes and model
    // ---------------------------------------------------------------------

    public function test_names_the_three_routes_under_the_totem_prefix(): void
    {
        $this->assertSame('/totem/one-off-runs', route('totem.one-off-runs.index', [], false));
        $this->assertSame('/totem/one-off-runs', route('totem.one-off-runs.store', [], false));
        $this->assertSame('/totem/one-off-runs/17', route('totem.one-off-runs.show', 17, false));
    }

    public function test_model_uses_the_totem_table_prefix_and_connection(): void
    {
        config(['totem.table_prefix' => 'custom_', 'totem.database_connection' => 'totem-conn']);

        $run = new OneOffCommandRun;

        $this->assertSame('custom_one_off_command_runs', $run->getTable());
        $this->assertSame('totem-conn', $run->getConnectionName());
    }

    // ---------------------------------------------------------------------
    // AC1: run an allowlisted command once
    // ---------------------------------------------------------------------

    public function test_offers_exactly_the_allowlisted_commands_in_a_form_that_posts_command_and_arguments(): void
    {
        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSee('action="'.url($this->runsPath()).'"', false)
            ->assertSee('name="command"', false)
            ->assertSee('name="arguments"', false)
            ->assertSee('value="totem-test:echo"', false)
            ->assertSee('value="totem-test:throws"', false)
            ->assertDontSee('value="totem-test:forbidden"', false);
    }

    public function test_runs_the_command_once_with_the_submitted_arguments_and_creates_no_task(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:echo',
            'arguments' => 'alpha --org=7',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->status());
        $response->assertSessionHasNoErrors();

        $run = OneOffCommandRun::query()->sole();

        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertSame(1, $this->echo->runs);
        $this->assertStringContainsString("info: target=alpha\n", $run->output);
        $this->assertStringContainsString("org='7'\n", $run->output);
        $this->assertSame(0, Task::query()->count());
    }

    public function test_refuses_a_command_totem_does_not_list_with_a_form_error_and_no_run(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:forbidden',
            'arguments' => '',
        ])->assertRedirect()->assertSessionHasErrors('command');

        $this->assertSame(0, $this->forbidden->runs);
        $this->assertSame(0, DB::table('one_off_command_runs')->count());
    }

    public function test_refuses_a_non_allowlisted_command_at_the_runner_running_and_recording_nothing(): void
    {
        $runner = $this->app->make(OneOffCommandRunner::class);

        try {
            $runner->run('totem-test:forbidden', null, $this->operator);
            $this->fail('The runner ran a command outside the allowlist.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('totem-test:forbidden', $e->getMessage());
        }

        $this->assertSame(0, $this->forbidden->runs);
        $this->assertSame(0, DB::table('one_off_command_runs')->count());
    }

    public function test_decides_allowance_with_totems_own_filter_patterns(): void
    {
        config(['totem.artisan.command_filter' => ['totem-test:ec*']]);

        $runner = $this->app->make(OneOffCommandRunner::class);

        $this->assertTrue($runner->allows('totem-test:echo'));
        $this->assertFalse($runner->allows('totem-test:org'));
        $this->assertFalse($runner->allows('totem-test:forbidden'));
    }

    public function test_honours_totems_blacklist_mode(): void
    {
        config([
            'totem.artisan.command_filter' => ['totem-test:echo', 'totem-test:forbidden'],
            'totem.artisan.whitelist' => false,
        ]);

        $runner = $this->app->make(OneOffCommandRunner::class);

        $this->assertFalse($runner->allows('totem-test:echo'));
        $this->assertFalse($runner->allows('totem-test:forbidden'));
        $this->assertTrue($runner->allows('totem-test:org'));
    }

    public function test_records_a_run_without_a_signed_in_user(): void
    {
        $run = $this->app->make(OneOffCommandRunner::class)->run('totem-test:org', '--org=3', null);

        $this->assertSame(1, $this->org->runs);
        $this->assertNull($run->user_id);
        $this->assertNull($run->user_email);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSeeInOrder(['Run by', 'Unknown']);
    }

    // ---------------------------------------------------------------------
    // AC2: the detail page shows output and exit code
    // ---------------------------------------------------------------------

    public function test_shows_the_normal_and_error_level_lines_and_the_integer_exit_code(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:echo',
            'arguments' => 'bravo --exit=213',
        ]);

        $run = OneOffCommandRun::query()->sole();

        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('info: target=bravo')
            ->assertSee('error: target=bravo')
            ->assertSee('org=NULL')
            ->assertSeeInOrder(['Exit code', '213']);
    }

    // ---------------------------------------------------------------------
    // AC3: every column of the run is stored
    // ---------------------------------------------------------------------

    public function test_stores_command_arguments_user_email_at_run_time_timing_exit_code_output_and_error(): void
    {
        $before = Carbon::now();

        $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:echo',
            'arguments' => 'charlie --org=42',
        ]);

        $after = Carbon::now();

        $this->operator->update(['email' => 'renamed@example.com']);

        $row = DB::table('one_off_command_runs')->sole();

        $this->assertSame('totem-test:echo', $row->command);
        $this->assertSame('charlie --org=42', $row->arguments);
        $this->assertEquals($this->operator->getKey(), $row->user_id);
        $this->assertSame('operator@example.com', $row->user_email);
        $this->assertEquals(0, $row->exit_code);
        $this->assertNotNull($row->exit_code);
        $this->assertSame("info: target=charlie\nerror: target=charlie\norg='42'\n", $row->output);
        $this->assertNull($row->error);

        $startedAt = Carbon::parse($row->started_at);
        $this->assertTrue($startedAt->betweenIncluded($before->copy()->startOfSecond(), $after));
        $this->assertGreaterThanOrEqual(30, (int) $row->duration_ms);
        $this->assertLessThanOrEqual((int) ceil($before->diffInMilliseconds($after)) + 1, (int) $row->duration_ms);
    }

    // ---------------------------------------------------------------------
    // AC4: history list and stable detail page
    // ---------------------------------------------------------------------

    public function test_lists_runs_newest_first_by_started_at_each_linking_to_its_detail_page(): void
    {
        // Inserted out of chronological order so id order (either direction) disagrees with
        // started_at order.
        $middle = $this->insertRun(['arguments' => 'zulu-middle', 'started_at' => Carbon::parse('2026-10-02 08:00:00')]);
        $newest = $this->insertRun(['arguments' => 'zulu-newest', 'started_at' => Carbon::parse('2026-10-03 08:00:00')]);
        $oldest = $this->insertRun(['arguments' => 'zulu-oldest', 'started_at' => Carbon::parse('2026-10-01 08:00:00')]);

        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSeeInOrder(['History', 'zulu-newest', 'zulu-middle', 'zulu-oldest'])
            ->assertSee('href="'.url($this->runsPath('/'.$newest)).'"', false)
            ->assertSee('href="'.url($this->runsPath('/'.$middle)).'"', false)
            ->assertSee('href="'.url($this->runsPath('/'.$oldest)).'"', false);
    }

    public function test_breaks_a_started_at_tie_by_newest_id_first(): void
    {
        $at = Carbon::parse('2026-10-02 08:00:00');
        $this->insertRun(['arguments' => 'tie-first-inserted', 'started_at' => $at]);
        $this->insertRun(['arguments' => 'tie-second-inserted', 'started_at' => $at]);

        // SQLite reads the started_at index backwards and so returns ties newest-id first even
        // without the tiebreak; other drivers need not. The query itself must order by both.
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'one_off_command_runs') && str_contains($query->sql, 'order by')) {
                $queries[] = $query->sql;
            }
        });

        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSeeInOrder(['tie-second-inserted', 'tie-first-inserted']);

        $this->assertCount(1, $queries);
        $this->assertStringEndsWith('order by "started_at" desc, "id" desc limit 50 offset 0', $queries[0]);
    }

    public function test_shows_the_history_columns_and_none_for_a_null_exit_code(): void
    {
        $this->insertRun(['arguments' => 'threw-run', 'exit_code' => null, 'duration_ms' => 987, 'user_email' => 'history-op@example.com']);

        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSeeInOrder(['Started', 'Command', 'Run by', 'Duration', 'Exit code'])
            ->assertSeeInOrder(['totem-test:echo threw-run', 'history-op@example.com', '987 ms', 'None']);
    }

    public function test_says_no_runs_yet_when_the_history_is_empty(): void
    {
        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSee('No runs yet.');

        $this->insertRun();

        $this->get($this->runsPath())->assertOk()->assertDontSee('No runs yet.');
    }

    public function test_paginates_the_history_fifty_per_page(): void
    {
        $base = Carbon::parse('2026-01-01 00:00:00');

        // 51 runs: page one holds the newest 50 (page-run-051 down to page-run-002).
        foreach (range(1, 51) as $i) {
            $this->insertRun(['arguments' => sprintf('page-run-%03d', $i), 'started_at' => $base->copy()->addMinutes($i)]);
        }

        $this->actingAs($this->operator)->get($this->runsPath())
            ->assertOk()
            ->assertSee('page-run-051')
            ->assertSee('page-run-002')
            ->assertDontSee('page-run-001')
            ->assertSee('page=2', false);

        $this->get($this->runsPath().'?page=2')
            ->assertOk()
            ->assertSee('page-run-001')
            ->assertDontSee('page-run-002');
    }

    public function test_renders_an_older_runs_detail_page_exactly_as_it_rendered_right_after_the_run(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:echo',
            'arguments' => 'delta --org=5',
        ]);

        $run = OneOffCommandRun::query()->sole();
        $rightAfter = $this->mainPanel($this->get($this->runsPath('/'.$run->getKey()))->assertOk()->getContent());

        $this->travel(3)->days();
        $this->operator->update(['email' => 'later@example.com']);
        $this->post($this->runsPath(), ['command' => 'totem-test:echo', 'arguments' => 'echo-later']);

        $this->assertSame(2, OneOffCommandRun::query()->count());

        $later = $this->mainPanel($this->get($this->runsPath('/'.$run->getKey()))->assertOk()->getContent());

        $this->assertNotSame('', $rightAfter);
        $this->assertStringContainsString('info: target=delta', $rightAfter);
        $this->assertStringContainsString('operator@example.com', $rightAfter);
        $this->assertSame($rightAfter, $later);
    }

    public function test_shows_the_start_time_as_a_fixed_date_in_the_app_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $id = $this->insertRun(['started_at' => '2026-10-01 12:34:56']);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$id))
            ->assertOk()
            ->assertSeeInOrder(['Started', '2026-10-01 12:34:56 UTC']);

        $this->get($this->runsPath())
            ->assertOk()
            ->assertSee('2026-10-01 12:34:56 UTC');
    }

    public function test_returns_404_for_a_run_that_does_not_exist(): void
    {
        $this->actingAs($this->operator)->get($this->runsPath('/999'))->assertNotFound();
    }

    public function test_links_back_to_the_runs_list_from_the_detail_page(): void
    {
        $id = $this->insertRun();

        $this->actingAs($this->operator)->get($this->runsPath('/'.$id))
            ->assertOk()
            ->assertSeeInOrder(['href="'.url($this->runsPath()).'"', 'Back to runs'], false);
    }

    // ---------------------------------------------------------------------
    // AC5: Failed and Succeeded
    // ---------------------------------------------------------------------

    public function test_shows_a_zero_exit_code_as_succeeded(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:echo', 'arguments' => 'echo-ok']);

        $run = OneOffCommandRun::query()->sole();

        $this->assertTrue($run->succeeded());
        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('totem-run-status-succeeded', false)
            ->assertSee('Succeeded')
            ->assertDontSee('Failed');
    }

    public function test_shows_a_non_zero_exit_code_as_failed(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:echo', 'arguments' => 'echo-three --exit=3']);

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(3, $run->exit_code);
        $this->assertFalse($run->succeeded());
        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('totem-run-status-failed', false)
            ->assertSee('Failed')
            ->assertDontSee('Succeeded');
    }

    public function test_records_a_throwing_run_as_failed_with_a_null_exit_code_the_exception_and_the_output_before_it(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:throws', 'arguments' => '']);

        $run = OneOffCommandRun::query()->sole();
        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertSame(1, $this->throws->runs);
        $this->assertNull($run->arguments);
        $this->assertNull($run->exit_code);
        $this->assertSame(RuntimeException::class.': fixture exploded', $run->error);
        $this->assertSame("before the throw\n", $run->output);
        $this->assertFalse($run->succeeded());

        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('Failed')
            ->assertDontSee('Succeeded')
            ->assertSeeInOrder(['Error', 'fixture exploded', 'Output', 'before the throw']);
    }

    public function test_records_a_run_that_throws_a_non_exception_error_as_failed_rather_than_failing_the_request(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:throws-error', 'arguments' => '']);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();
        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertSame(1, $this->throwsError->runs);
        $this->assertNull($run->exit_code);
        $this->assertSame(TypeError::class.': boom-type-error', $run->error);
        $this->assertSame("before the error\n", $run->output);
        $this->assertFalse($run->succeeded());

        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('Failed')
            ->assertDontSee('Succeeded');
    }

    public function test_omits_the_error_section_when_the_run_did_not_throw(): void
    {
        $id = $this->insertRun(['exit_code' => 1, 'error' => null]);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$id))
            ->assertOk()
            ->assertDontSee('<h5>Error</h5>', false)
            ->assertSee('<h5>Output</h5>', false);
    }

    public function test_shows_none_in_place_of_an_empty_output_box(): void
    {
        $empty = $this->insertRun(['output' => '', 'exit_code' => null, 'error' => 'RuntimeException: no output']);
        $printed = $this->insertRun(['output' => "printed line\n"]);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$empty))
            ->assertOk()
            ->assertSeeInOrder(['Output', '(None)'])
            ->assertDontSee('<pre></pre>', false);

        $this->get($this->runsPath('/'.$printed))
            ->assertOk()
            ->assertSee('<pre>printed line</pre>', false)
            ->assertDontSee('(None)');
    }

    /**
     * Laravel's styled lines ($this->components->info()) wrap the message in blank lines, and
     * HTML drops only the first newline after <pre>, so the box showed an empty line below the
     * text and none above it. The view trims surrounding line breaks; the stored output keeps them.
     */
    public function test_trims_surrounding_blank_lines_from_the_output_box_but_keeps_indentation(): void
    {
        $id = $this->insertRun(['output' => "\n  INFO  No supervisors are running.\n\n  second line\n\n"]);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$id))
            ->assertOk()
            ->assertSee("<pre>  INFO  No supervisors are running.\n\n  second line</pre>", false);

        $this->assertSame("\n  INFO  No supervisors are running.\n\n  second line\n\n", DB::table('one_off_command_runs')->where('id', $id)->value('output'));
    }

    public function test_shows_none_for_output_that_is_only_blank_lines(): void
    {
        $id = $this->insertRun(['output' => "\n\r\n\n"]);

        $this->actingAs($this->operator)->get($this->runsPath('/'.$id))
            ->assertOk()
            ->assertSeeInOrder(['Output', '(None)'])
            ->assertDontSee('<pre>', false);
    }

    public static function exitCodes(): array
    {
        return [
            'zero' => [0, true],
            'one' => [1, false],
            'three' => [3, false],
            'null (threw)' => [null, false],
        ];
    }

    #[DataProvider('exitCodes')]
    public function test_treats_exit_code_zero_as_the_only_succeeded_state(?int $exitCode, bool $succeeded): void
    {
        $this->assertSame($succeeded, (new OneOffCommandRun)->forceFill(['exit_code' => $exitCode])->succeeded());
    }

    // ---------------------------------------------------------------------
    // AC6: Totem's gate on every new route
    // ---------------------------------------------------------------------

    public static function routes(): array
    {
        return [
            'index' => ['get', ''],
            'store' => ['post', ''],
            'show' => ['get', '/{seeded}'],
        ];
    }

    #[DataProvider('routes')]
    public function test_refuses_a_guest_and_runs_nothing(string $method, string $suffix): void
    {
        $seeded = $this->insertRun();

        $this->{$method}($this->runsPath(str_replace('{seeded}', (string) $seeded, $suffix)), ['command' => 'totem-test:echo', 'arguments' => 'guest'])
            ->assertForbidden();

        $this->assertSame(0, $this->echo->runs);
        $this->assertSame(1, DB::table('one_off_command_runs')->count());
    }

    #[DataProvider('routes')]
    public function test_refuses_a_signed_in_user_totem_rejects_and_runs_nothing(string $method, string $suffix): void
    {
        $seeded = $this->insertRun();

        $this->actingAs(TestUser::factory()->create())
            ->{$method}($this->runsPath(str_replace('{seeded}', (string) $seeded, $suffix)), ['command' => 'totem-test:echo', 'arguments' => 'intruder'])
            ->assertForbidden();

        $this->assertSame(0, $this->echo->runs);
        $this->assertSame(1, DB::table('one_off_command_runs')->count());
    }

    // ---------------------------------------------------------------------
    // AC7: arguments parse with Symfony console input rules
    // ---------------------------------------------------------------------

    public function test_passes_a_valueless_optional_value_option_as_null_not_true_or_one(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:org', 'arguments' => '--org']);

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(1, $this->org->runs);
        $this->assertSame("org=NULL\n", $run->output);
        $this->assertSame(0, $run->exit_code);
    }

    public function test_records_a_valueless_required_value_option_as_a_failed_run_the_command_never_handled(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:required', 'arguments' => '--org']);

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(0, $this->required->runs);
        $this->assertNotSame(0, $run->exit_code);
        $this->assertFalse($run->succeeded());
        $this->assertStringContainsString('The "--org" option requires a value.', $run->output.$run->error);

        $this->get($this->runsPath('/'.$run->getKey()))->assertOk()->assertSee('Failed');
    }

    // ---------------------------------------------------------------------
    // Run input, stored output and rendering
    // ---------------------------------------------------------------------

    public function test_runs_the_command_non_interactively_so_confirm_takes_its_default_without_prompting(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:confirms', 'arguments' => '']);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(1, $this->confirms->runs);
        $this->assertNull($run->error);
        $this->assertSame(0, $run->exit_code);
        $this->assertStringContainsString("interactive=false\n", $run->output);
        $this->assertStringContainsString("declined\n", $run->output);
        $this->assertStringNotContainsString('confirmed', $run->output);
        $this->assertStringNotContainsString('Proceed?', $run->output);
    }

    public function test_keeps_the_run_non_interactive_when_the_arguments_end_option_parsing_with_double_dash(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:confirms', 'arguments' => '-- extra']);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(1, $this->confirms->runs);
        $this->assertNull($run->error);
        $this->assertStringContainsString("interactive=false\n", $run->output);
        $this->assertStringNotContainsString('Proceed?', $run->output);
    }

    /**
     * SQLite stores a NUL byte and invalid UTF-8 as given, so only the runner's sanitizer keeps
     * them out of the stored text here. On Postgres the same bytes would truncate the value or
     * fail the insert.
     */
    public function test_stores_output_holding_a_nul_byte_or_invalid_utf8_as_readable_valid_utf8(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:binary', 'arguments' => '']);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();
        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertSame(1, $this->binary->runs);
        $this->assertSame(0, $run->exit_code);
        $this->assertTrue(mb_check_encoding($run->output, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $run->output);
        $this->assertStringContainsString("beforeafter\n", $run->output);
        $this->assertStringContainsString("bad ?( utf8\n", $run->output);
    }

    public function test_stores_an_exception_message_holding_a_nul_byte_or_invalid_utf8_as_readable_valid_utf8(): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:throws-binary', 'arguments' => '']);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();
        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertSame(1, $this->throwsBinary->runs);
        $this->assertTrue(mb_check_encoding($run->error, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $run->error);
        $this->assertSame(RuntimeException::class.': xy bad ?( z', $run->error);
    }

    public function test_hands_the_command_the_submitted_arguments_byte_for_byte_before_any_storage_repair(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:org-hex', 'arguments' => "--org=\xC3\x28"]);

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame(1, $this->orgHex->runs);
        $this->assertStringContainsString("org-hex=c328\n", $run->output);
    }

    public static function badArguments(): array
    {
        return [
            'NUL byte' => ["--org=7\0x", '--org=7x'],
            'invalid UTF-8' => ["--org=\xC3\x28", '--org=?('],
        ];
    }

    #[DataProvider('badArguments')]
    public function test_stores_arguments_holding_a_nul_byte_or_invalid_utf8_as_valid_utf8_keeping_the_text_after_the_bad_byte(string $arguments, string $stored): void
    {
        $response = $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:org', 'arguments' => $arguments]);

        $this->assertSame(Response::HTTP_FOUND, $response->status());

        $run = OneOffCommandRun::query()->sole();
        $response->assertRedirect(url($this->runsPath('/'.$run->getKey())));

        $this->assertTrue(mb_check_encoding($run->arguments, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $run->arguments);
        $this->assertSame($stored, $run->arguments);
    }

    public function test_escapes_markup_in_the_command_line_and_output_on_the_detail_page(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), [
            'command' => 'totem-test:echo',
            'arguments' => '<script>x</script>',
        ]);

        $run = OneOffCommandRun::query()->sole();

        $this->assertSame('<script>x</script>', $run->arguments);
        $this->assertStringContainsString('info: target=<script>x</script>', $run->output);

        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('totem-test:echo <script>x</script>')
            ->assertSee('info: target=<script>x</script>')
            ->assertDontSee('<script>x</script>', false);

        $this->get($this->runsPath())
            ->assertOk()
            ->assertSee('totem-test:echo <script>x</script>')
            ->assertDontSee('<script>x</script>', false);
    }

    public function test_escapes_markup_in_the_stored_error_on_the_detail_page(): void
    {
        $this->actingAs($this->operator)->post($this->runsPath(), ['command' => 'totem-test:throws-markup', 'arguments' => '']);

        $run = OneOffCommandRun::query()->sole();

        $this->assertStringContainsString('<b>boom</b>', $run->error);

        $this->get($this->runsPath('/'.$run->getKey()))
            ->assertOk()
            ->assertSee('<b>boom</b>')
            ->assertDontSee('<b>boom</b>', false);
    }

    // ---------------------------------------------------------------------
    // AC8: the in-request timeout notice, and the sidebar
    // ---------------------------------------------------------------------

    public function test_warns_inside_the_run_form_and_above_its_submit_button_that_a_long_command_times_out(): void
    {
        $html = $this->actingAs($this->operator)->get($this->runsPath())->assertOk()->getContent();

        $formStart = strpos($html, 'action="'.e(url($this->runsPath())).'"');
        $this->assertNotFalse($formStart);

        $formEnd = strpos($html, '</form>', $formStart);
        $this->assertNotFalse($formEnd);

        $form = substr($html, $formStart, $formEnd - $formStart);
        $submit = strpos($form, 'type="submit"');
        $this->assertNotFalse($submit);

        $aboveSubmit = substr($form, 0, $submit);
        $this->assertStringContainsString('uk-alert-warning', $aboveSubmit);

        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($aboveSubmit), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $this->assertStringContainsString(
            "The command runs inside this page's request. A command that runs longer than the request time limit times out, so schedule long work as a task instead.",
            $text,
        );
    }

    public function test_renders_the_run_once_link_after_upcoming_in_the_sidebar(): void
    {
        $this->actingAs($this->operator)->get('/'.config('totem.web.route_prefix').'/tasks')
            ->assertOk()
            ->assertSeeInOrder([
                '<aside',
                'Upcoming',
                'href="'.url($this->runsPath()).'"',
                'Run once',
                '</aside>',
            ], false);
    }

    public function test_marks_only_run_once_active_in_the_sidebar_on_the_run_once_pages(): void
    {
        $id = $this->insertRun();

        foreach ([$this->runsPath(), $this->runsPath('/'.$id)] as $path) {
            $html = $this->actingAs($this->operator)->get($path)->assertOk()->getContent();
            $aside = substr($html, (int) strpos($html, '<aside'), (int) strpos($html, '</aside>') - (int) strpos($html, '<aside'));

            $this->assertSame(1, substr_count($aside, 'uk-active'), $path);
            $this->assertMatchesRegularExpression('/<li class="uk-active">\s*<a href="'.preg_quote(url($this->runsPath()), '/').'"/', $aside, $path);
        }
    }
}

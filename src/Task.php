<?php

namespace Studio\Totem;

use Carbon\Carbon;
use Cron\CronExpression;
use Database\Factories\TotemTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Studio\Totem\Traits\FrontendSortable;
use Studio\Totem\Traits\HasFrequencies;

class Task extends TotemModel
{
    use Notifiable, HasFrequencies, FrontendSortable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'description',
        'command',
        'parameters',
        'expression',
        'timezone',
        'is_active',
        'dont_overlap',
        'run_in_maintenance',
        'notification_email_address',
        'notification_phone_number',
        'notification_slack_webhook',
        'auto_cleanup_type',
        'auto_cleanup_num',
        'run_on_one_server',
        'run_in_background',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'activated',
        'upcoming',
        'last_result',
        'average_runtime',
    ];

    /**
     * Activated Accessor.
     *
     * @return bool
     */
    public function getActivatedAttribute()
    {
        return $this->is_active;
    }

    /**
     * Upcoming Accessor.
     *
     * @return string
     */
    public function getUpcomingAttribute()
    {
        return CronExpression::factory($this->getCronExpression())->getNextRunDate()->format('Y-m-d H:i:s');
    }

    /**
     * Convert a string of command arguments and options to an array.
     *
     * @param  bool  $console  if true will convert arguments to non associative array
     * @return array
     */
    public function compileParameters($console = false)
    {
        if ($this->parameters) {
            $regex = '/(?=\S)[^\'"\s]*(?:\'[^\']*\'[^\'"\s]*|"[^"]*"[^\'"\s]*)*/';
            preg_match_all($regex, $this->parameters, $matches, PREG_SET_ORDER, 0);

            $argument_index = 0;

            $duplicate_parameter_index = function (array $carry, array $param, string $trimmed_param) {
                if (! isset($carry[$param[0]])) {
                    $carry[$param[0]] = $trimmed_param;
                } else {
                    if (! is_array($carry[$param[0]])) {
                        $carry[$param[0]] = [$carry[$param[0]]];
                    }
                    $carry[$param[0]][] = $trimmed_param;
                }

                return $carry;
            };

            return collect($matches)->reduce(function ($carry, $parameter) use ($console, &$argument_index, $duplicate_parameter_index) {
                $param = explode('=', $parameter[0], 2);

                if (count($param) > 1) {
                    $trimmed_param = trim(trim($param[1], '"'), "'");
                    if ($console) {
                        if (Str::startsWith($param[0], ['--', '-'])) {
                            $carry = $duplicate_parameter_index($carry, $param, $trimmed_param);
                        } else {
                            $carry[$argument_index++] = $trimmed_param;
                        }

                        return $carry;
                    }

                    return $duplicate_parameter_index($carry, $param, $trimmed_param);
                }

                Str::startsWith($param[0], ['--', '-']) && ! $console ?
                    $carry[$param[0]] = true :
                    $carry[$argument_index++] = $param[0];

                return $carry;
            }, []);
        }

        return [];
    }

    /**
     * Results Relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function results()
    {
        return $this->hasMany(Result::class, 'task_id', 'id');
    }

    /**
     * Returns the most recent result entry for this task.
     *
     * @return Model|null
     */
    public function getLastResultAttribute()
    {
        return $this->results()->orderBy('id', 'desc')->first();
    }

    /**
     * @return float
     */
    public function getAverageRuntimeAttribute()
    {
        return $this->results()->avg('duration') ?? 0.00;
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return string
     */
    public function routeNotificationForMail()
    {
        return $this->notification_email_address;
    }

    /**
     * Route notifications for the Nexmo channel.
     *
     * @return string
     */
    public function routeNotificationForNexmo()
    {
        return $this->notification_phone_number;
    }

    /**
     * Route notifications for the Slack channel.
     *
     * @return string
     */
    public function routeNotificationForSlack()
    {
        return $this->notification_slack_webhook;
    }

    /**
     * Attempt to perform clean on task results.
     */
    public function autoCleanup()
    {
        if ($this->auto_cleanup_num > 0) {
            if ($this->auto_cleanup_type === 'results') {
                $oldest_id = self::results()
                    ->orderBy('ran_at', 'desc')
                    ->limit($this->auto_cleanup_num)
                    ->get()
                    ->min('id');
                do {
                    $rowsDeleted = self::results()
                        ->where('id', '<', $oldest_id)
                        ->limit(500)
                        ->getQuery()
                        ->delete();
                } while ($rowsDeleted > 0);
            } else {
                do {
                    $rowsDeleted = self::results()
                        ->where('ran_at', '<', Carbon::now()->subDays($this->auto_cleanup_num - 1))
                        ->limit(500)
                        ->getQuery()
                        ->delete();
                } while ($rowsDeleted > 0);
            }
        }
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return TotemTaskFactory::new();
    }
}

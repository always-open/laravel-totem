@extends('totem::layout')
@section('page-title')
    @parent
    - {{ $task->exists ? 'Update' : 'Create'}} Task
@stop
@section('main-panel-before')
    <form method="POST">
        {{csrf_field()}}
@stop
@section('title')
    <div class="uk-flex uk-flex-between uk-flex-middle">
        <h5 class="uk-card-title uk-margin-remove">{{ $task->exists ? 'Update' : 'Create'}} Task</h5>
    </div>
@stop
@section('main-panel-content')
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Description</label>
            <div class="uk-text-meta">Provide a descriptive name for your task</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <input class="uk-input" placeholder="e.g. Daily Backups" name="description" id="description" value="{{old('description', $task->description)}}" type="text">
            @if($errors->has('description'))
                <p class="uk-text-danger">{{$errors->first('description')}}</p>
            @endif
        </div>
    </div>
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Command</label>
            <div class="uk-text-meta">Select an artisan command to schedule</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <command-list command="{{ $task->command }}" :commands="{{ json_encode($commands) }}"></command-list>
            @if($errors->has('command'))
                <p class="uk-text-danger">{{$errors->first('command')}}</p>
            @endif
        </div>
    </div>
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Parameters (Optional)</label>
            <div class="uk-text-meta">Command parameters required to run the selected command</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <input class="uk-input" placeholder="e.g. --type=all for options or name=John for arguments" name="parameters" id="parameters" value="{{old('parameters', $task->parameters)}}" type="text">
        </div>
    </div>
    <hr class="uk-divider-icon">
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Timezone</label>
            <div class="uk-text-meta">Select a timezone for your task. App timezone is selected by default</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <select id="timezone" name="timezone" class="uk-select" placeholder="Select a timezone">
                @foreach ($timezones as $key => $timezone)
                    <option value="{{$timezone}}" {{old('timezone', $task->exists ? $task->timezone :  config('app.timezone')) == $timezone ? 'selected' : ''}}>{{$timezone}}</option>
                @endforeach
            </select>
        </div>
    </div>
    <task-type inline-template current="{{old('type', $task->expression ? 'expression' : 'frequency')}}" :existing="{{old('frequencies') ? json_encode(old('frequencies')) : $task->frequencies}}" >
        <div class="uk-margin">
            <div class="uk-grid">
                <div class="uk-width-1-1@s uk-width-1-3@m">
                    <div class="uk-form-label">Type</div>
                    <div class="uk-text-meta">Choose whether to define a cron expression or to add frequencies</div>
                </div>
                <div class="uk-width-1-1@s uk-width-2-3@m uk-form-controls-text">
                    <label>
                        <input type="radio" name="type" v-model="type" value="expression"> Expression
                    </label><br>
                    <label>
                        <input type="radio" name="type" v-model="type" value="frequency"> Frequencies
                    </label>
                </div>
            </div>
            <div class="uk-grid" v-if="isCron">
                <div class="uk-width-1-1@s uk-width-1-3@m">
                    <label class="uk-form-label">Cron Expression</label>
                    <div class="uk-text-meta">Add a cron expression for your task</div>
                </div>
                <div class="uk-width-1-1@s uk-width-2-3@m">
                    <input class="uk-input" placeholder="e.g * * * * * to run this task all the time" name="expression" id="expression" value="{{old('expression', $task->expression)}}" type="text">
                    @if($errors->has('expression'))
                        <p class="uk-text-danger">{{$errors->first('expression')}}</p>
                    @endif
                </div>
            </div>
            <div class="uk-grid" v-if="managesFrequencies">
                <div class="uk-width-1-1@s uk-width-1-3@m">
                    <label class="uk-form-label">Frequencies</label>
                    <div class="uk-text-meta">Add frequencies to your task. These frequencies will be converted into a cron expression while scheduling the task</div>
                </div>
                <div class="uk-width-1-1@s uk-width-2-3@m">
                    <a class="uk-button uk-button-small uk-button-link" @click.self.prevent="showModal = true">Add Frequency</a>
                    @include('totem::dialogs.frequencies.add')
                    <table class="uk-table uk-table-divider uk-margin-remove">
                        <thead>
                            <tr>
                                <th class="uk-padding-remove-left">
                                    Frequency
                                </th>
                                <th class="uk-padding-remove-left">
                                    Parameters
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(frequency, index) in frequencies">
                                <td class="uk-padding-remove-left">
                                    @{{ frequency.label }}
                                    <input type="hidden" :name="'frequencies[' + index + '][interval]'" v-model="frequency.interval">
                                    <input type="hidden" :name="'frequencies[' + index + '][label]'" v-model="frequency.label">
                                </td>
                                <td class="uk-padding-remove-left">
                                    <span v-if="frequency.parameters && frequency.parameters.length > 0">
                                        <span v-for="(parameter, key) in frequency.parameters">
                                            @{{ parameter.value }}
                                            <span v-if="frequency.parameters.length > 1 && key < frequency.parameters.length - 1">,</span>
                                            <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key +'][name]'" v-model="parameter.name">
                                            <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key +'][value]'" v-model="parameter.value">
                                        </span>
                                    </span>
                                    <span v-else>
                                        No Parameters
                                    </span>
                                </td>
                                <td>
                                    <a class="uk-button uk-button-link" @click="remove(index)">
                                        <span uk-icon="icon: close"></span>
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="frequencies.length == 0">
                                <td colspan="3" class="uk-padding-remove-left">No Frequencies Found</td>
                            </tr>
                        </tbody>
                    </table>
                    @if($errors->has('frequencies'))
                        <p class="uk-text-danger">{{$errors->first('frequencies')}}</p>
                    @endif
                </div>
            </div>
        </div>
    </task-type>
    <hr class="uk-divider-icon">
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Email Notification (optional)</label>
            <div class="uk-text-meta">Add an email address to receive notifications when this task gets executed. Leave empty if you do not wish to receive email notifications</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <input type="text" id="email" name="notification_email_address" value="{{old('notification_email_address', $task->notification_email_address)}}" class="uk-input" placeholder="e.g. john.doe@name.tld">
            @if($errors->has('notification_email_address'))
                <p class="uk-text-danger">{{$errors->first('notification_email_address')}}</p>
            @endif
        </div>
    </div>
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">SMS Notification (optional)</label>
            <div class="uk-text-meta">Add a phone number to receive SMS notifications. Leave empty if you do not wish to receive sms notifications</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <input type="text" id="phone" name="notification_phone_number" value="{{old('notification_phone_number', $task->notification_phone_number)}}" class="uk-input" placeholder="e.g. 18701234567">
            @if($errors->has('notification_phone_number'))
                <p class="uk-text-danger">{{$errors->first('notification_phone_number')}}</p>
            @endif
        </div>
    </div>
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <label class="uk-form-label">Slack Notification (optional)</label>
            <div class="uk-text-meta">Add a slack web hook url to recieve slack notifications. Leave empty if you do not wish to receive slack notifications</div>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m">
            <input type="text" id="slack" name="notification_slack_webhook" value="{{old('notification_slack_webhook', $task->notification_slack_webhook)}}" class="uk-input" placeholder="e.g. https://hooks.slack.com/TXXXXX/BXXXXX/XXXXXXXXXX">
            @if($errors->has('notification_slack_webhook'))
                <p class="uk-text-danger">{{$errors->first('notification_slack_webhook')}}</p>
            @endif
        </div>
    </div>
    <hr class="uk-divider-icon">
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <div class="uk-form-label">Miscellaneous Options</div>
            <ul class="uk-list uk-padding-remove">
                <li class="uk-text-meta">Decide whether multiple instances of same task should overlap each other or not.</li>
                <li class="uk-text-meta">Decide whether the task should be executed while the app is in maintenance mode.</li>
                <li class="uk-text-meta">Decide whether the task should be executed on a single server.</li>
                <li class="uk-text-meta">Decide whether the task should be executed in the background.</li>
            </ul>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m uk-form-controls-text">
            <label class="uk-margin">
                <input type="hidden" name="dont_overlap" id="dont_overlap" value="0" {{old('dont_overlap', $task->dont_overlap) ? '' : 'checked'}}>
                <input type="checkbox" name="dont_overlap" id="dont_overlap" value="1" {{old('dont_overlap', $task->dont_overlap) ? 'checked' : ''}}>
                Don't Overlap
            </label>

            <div class="uk-margin">
                <label class="uk-margin">
                    <input type="hidden" name="run_in_maintenance" id="run_in_maintenance" value="0" {{old('run_in_maintenance', $task->run_in_maintenance) ? '' : 'checked'}}>
                    <input type="checkbox" name="run_in_maintenance" id="run_in_maintenance" value="1" {{old('run_in_maintenance', $task->run_in_maintenance) ? 'checked' : ''}}>
                    Run in maintenance mode
                </label>
            </div>
            <div class="uk-margin">
                <label class="uk-margin">
                    <input type="hidden" name="run_on_one_server" id="run_on_one_server" value="0" {{old('run_on_one_server', $task->run_on_one_server) ? '' : 'checked'}}>
                    <input type="checkbox" name="run_on_one_server" id="run_on_one_server" value="1" {{old('run_on_one_server', $task->run_on_one_server) ? 'checked' : ''}}>
                    Run on a single server
                </label>
            </div>
            <div class="uk-margin">
                <label class="uk-margin">
                    <input type="hidden" name="run_in_background" id="run_in_background" value="0" {{old('run_in_background', $task->run_in_background) ? '' : 'checked'}}>
                    <input type="checkbox" name="run_in_background" id="run_in_background" value="1" {{old('run_in_background', $task->run_in_background) ? 'checked' : ''}}>
                    Run in the background
                </label>
            </div>
        </div>
    </div>
    <hr class="uk-divider-icon">
    <div class="uk-grid">
        <div class="uk-width-1-1@s uk-width-1-3@m">
            <div class="uk-form-label">Cleanup Options</div>
            <ul class="uk-list uk-padding-remove">
                <li class="uk-text-meta">Determine if an over-abundance of results will be removed after a set limit or age. Set non-zero value to enable.</li>
            </ul>
        </div>
        <div class="uk-width-1-1@s uk-width-2-3@m uk-form-controls-text">
            <label class="uk-margin">
                Auto Cleanup results after
                <br>
                <input class="uk-input" type="number" name="auto_cleanup_num" id="auto_cleanup_num" value="{{ old('auto_cleanup_num', $task->auto_cleanup_num) ?? 0 }}" />
                <br>
                <label>
                    <input type="radio" name="auto_cleanup_type" value="days" {{old('auto_cleanup_type', $task->auto_cleanup_type) !== 'results' ? 'checked' : ''}}> Days
                </label><br>
                <label>
                    <input type="radio" name="auto_cleanup_type" value="results" {{old('auto_cleanup_type', $task->auto_cleanup_type) === 'results' ? 'checked' : ''}}> Results
                </label>
            </label>
        </div>
    </div>
@stop
@section('main-panel-footer')
    <button class="uk-button uk-button-primary uk-button-small" type="submit">Save</button>
    @if($task->exists)
        <a href="{{ route('totem.task.view', ['totemTask' => $task]) }}" class="uk-button uk-button-secondary uk-button-small">Cancel</a>
    @else
        <a href="{{ route('totem.tasks.all') }}" class="uk-button uk-button-secondary uk-button-small">Cancel</a>
    @endif
@stop
@section('main-panel-after')
    </form>
@stop

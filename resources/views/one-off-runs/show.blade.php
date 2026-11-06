@extends('totem::layout')
@section('page-title')
    @parent
    - One-off run
@stop
@section('title')
    <div class="uk-flex uk-flex-between uk-flex-middle">
        <h5 class="uk-card-title uk-margin-remove">One-off run</h5>
        @if($run->succeeded())
            <span class="uk-label totem-run-status-succeeded">Succeeded</span>
        @else
            <span class="uk-label totem-run-status-failed">Failed</span>
        @endif
    </div>
@stop
@section('main-panel-content')
    <ul class="uk-list uk-list-striped">
        <li>
            <span class="uk-text-muted uk-float-right">Command</span>
            <span class="uk-float-left"><code>{{ $run->commandLine() }}</code></span>
        </li>
        <li>
            <span class="uk-text-muted uk-float-right">Run by</span>
            <span class="uk-float-left">{{ $run->user_email ?? 'Unknown' }}</span>
        </li>
        <li>
            <span class="uk-text-muted uk-float-right">Started</span>
            <span class="uk-float-left">{{ $run->started_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</span>
        </li>
        <li>
            <span class="uk-text-muted uk-float-right">Duration</span>
            <span class="uk-float-left">{{ $run->duration_ms }} ms</span>
        </li>
        <li>
            <span class="uk-text-muted uk-float-right">Exit code</span>
            <span class="uk-float-left">{{ $run->exit_code ?? 'None' }}</span>
        </li>
    </ul>
    @if($run->error !== null)
        <h5>Error</h5>
        <pre>{{ $run->error }}</pre>
    @endif
    <h5>Output</h5>
    {{-- Laravel's styled lines are wrapped in blank lines, and HTML drops only the first newline
         after <pre>, so surrounding line breaks are trimmed for display. Indentation is kept. --}}
    @php($output = trim($run->output, "\r\n"))
    @if($output === '')
        <p class="uk-text-muted">(None)</p>
    @else
        <pre>{{ $output }}</pre>
    @endif
@stop
@section('main-panel-footer')
    <a class="uk-button uk-button-default uk-button-small" href="{{ route('totem.one-off-runs.index') }}">Back to runs</a>
@stop

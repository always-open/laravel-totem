@extends('totem::layout')
@section('page-title')
    @parent
    - Run once
@stop
@section('title')
    <h4 class="uk-card-title uk-margin-remove">Run once</h4>
@stop
@section('main-panel-content')
    <form method="POST" action="{{ route('totem.one-off-runs.store') }}" class="uk-form-stacked">
        @csrf
        <div class="uk-margin">
            <label class="uk-form-label" for="one-off-command">Command</label>
            <select id="one-off-command" name="command" class="uk-select">
                @foreach($commands as $command)
                    <option value="{{ $command }}" @selected(old('command') === $command)>{{ $command }}</option>
                @endforeach
            </select>
            @error('command')
                <p class="uk-text-danger uk-margin-small-top">{{ $message }}</p>
            @enderror
        </div>
        <div class="uk-margin">
            <label class="uk-form-label" for="one-off-arguments">Arguments</label>
            <input id="one-off-arguments" type="text" name="arguments" value="{{ old('arguments') }}" class="uk-input" placeholder="--org=1 --dry-run">
            @error('arguments')
                <p class="uk-text-danger uk-margin-small-top">{{ $message }}</p>
            @enderror
        </div>
        <div class="uk-alert uk-alert-warning">
            The command runs inside this page's request. A command that runs longer than the request time limit times out, so schedule long work as a task instead.
        </div>
        <button type="submit" class="uk-button uk-button-primary">Run</button>
    </form>
@stop
@section('additional-panels')
    <div class="uk-card uk-card-default uk-margin-top">
        <div class="uk-card-header">
            <h5 class="uk-card-title uk-margin-remove">History</h5>
        </div>
        <div class="uk-card-body">
            <table class="uk-table uk-table-responsive uk-table-divider">
                <thead>
                    <tr>
                        <th>Started</th>
                        <th>Command</th>
                        <th>Run by</th>
                        <th>Duration</th>
                        <th>Exit code</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('totem.one-off-runs.show', $run) }}">{{ $run->started_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</a>
                            </td>
                            <td><code>{{ $run->commandLine() }}</code></td>
                            <td>{{ $run->user_email ?? 'Unknown' }}</td>
                            <td>{{ $run->duration_ms }} ms</td>
                            <td>{{ $run->exit_code ?? 'None' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="uk-text-center" colspan="5">No runs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="uk-card-footer">
            {{ $runs->links('totem::partials.pagination') }}
        </div>
    </div>
@stop

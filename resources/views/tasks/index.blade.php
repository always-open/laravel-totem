@extends("totem::layout")
@section('page-title')
    @parent
    - Tasks
@stop
@section('title')
    <div class="uk-flex uk-flex-between uk-flex-middle">
        <h4 class="uk-card-title uk-margin-remove">Tasks</h4>
        <task-search
            action="{{ request()->fullUrl() }}"
            value="{{ is_string(request('q')) ? request('q') : '' }}"></task-search>
    </div>
@stop
@if($pause)
@section('main-panel-before')
    <div class="uk-alert uk-alert-warning uk-flex uk-flex-between uk-flex-middle">
        <span>
            All scheduled tasks are paused since {{ $pause->paused_at->format('Y-m-d H:i') }}
            &middot;
            {{ $pause->resume_at ? 'resuming automatically at '.$pause->resume_at->format('Y-m-d H:i') : 'until resumed manually' }}
        </span>
        <form method="POST" action="{{ route('totem.tasks.resume') }}" class="uk-margin-remove">
            @csrf
            @method('DELETE')
            <button type="submit" class="uk-button uk-button-primary uk-button-small">Resume</button>
        </form>
    </div>
@stop
@endif
@section('main-panel-content')
    <task-search-region name="table">
    <table class="uk-table uk-table-responsive" cellpadding="0" cellspacing="0" class="mb1">
        <thead>
            <tr>
                <th>{!! \Studio\Totem\Helpers\columnSort('Description', 'description') !!}</th>
                <th>{!! \Studio\Totem\Helpers\columnSort('Average Runtime', 'average_runtime') !!}</th>
                <th>{!! \Studio\Totem\Helpers\columnSort('Last Run', 'last_ran_at') !!}</th>
                <th>Next Run</th>
                <th class="uk-text-center">Execute</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $task)
                <tr is="vue:task-row"
                    :data-task="{{ $task }}"
                    show-href="{{ route('totem.task.view', ['totemTask' => $task]) }}"
                    execute-href="{{ route('totem.task.execute', ['totemTask' => $task]) }}"
                ></tr>
            @empty
                <tr>
                    <td class="uk-text-center" colspan="5">
                        <img class="uk-svg" width="50" height="50" src="{{asset('/vendor/totem/img/funnel.svg')}}">
                        <p>No Tasks Found.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </task-search-region>
@stop
@section('main-panel-footer')
    <div class="uk-flex uk-flex-between">
        <span>
            <a class="uk-icon-button uk-button-primary uk-hidden@m" uk-icon="icon: plus" href="{{route('totem.task.create')}}"></a>
            <a class="uk-button uk-button-primary uk-button-small uk-visible@m" href="{{route('totem.task.create')}}">New Task</a>
        </span>

        @unless($pause)
        <form method="POST" action="{{ route('totem.tasks.pause') }}" class="uk-flex uk-flex-middle uk-margin-remove">
            @csrf
            <select name="duration" class="uk-select uk-form-small uk-form-width-small" aria-label="Pause duration">
                <option value="">Until resumed</option>
                @foreach($durations as $minutes => $label)
                    <option value="{{ $minutes }}" @selected(old('duration') == $minutes)>For {{ $label }}</option>
                @endforeach
            </select>
            <input type="datetime-local" name="until" value="{{ old('until') }}" class="uk-input uk-form-small uk-form-width-medium uk-margin-small-left" aria-label="Or resume at" title="Or resume at a specific time">
            <button type="submit" class="uk-button uk-button-danger uk-button-small uk-margin-small-left">Pause All</button>
        </form>
        @endunless

        <span>
            <import-button url="{{route('totem.tasks.import')}}"></import-button>
            <a class="uk-icon-button uk-button-primary uk-hidden@m" uk-icon="icon: cloud-download"  href="{{route('totem.tasks.export')}}"></a>
            <a class="uk-button uk-button-primary uk-button-small uk-visible@m" href="{{route('totem.tasks.export')}}">Export</a>
        </span>
    </div>
    @foreach(['duration', 'until'] as $field)
        @error($field)
            <p class="uk-text-danger uk-text-small uk-text-center">{{ $message }}</p>
        @enderror
    @endforeach
    <task-search-region name="pagination">
        {{$tasks->links('totem::partials.pagination', ['params' => '&' . http_build_query(array_filter(request()->except('page')))])}}
    </task-search-region>
@stop

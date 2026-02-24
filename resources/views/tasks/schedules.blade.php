@extends("totem::layout")
@section('page-title')
    @parent
    - Upcoming
@stop
@section('title')
    <h4 class="uk-card-title uk-margin-remove">Upcoming Schedule</h4>
@stop
@section('main-panel-content')
    <upcoming-calendar
        events-url="{{ route('totem.upcoming.events') }}"
        task-base-url="{{ route('totem.tasks.all') }}"
    ></upcoming-calendar>
@stop

<aside class="uk-width-1-6@l uk-margin-bottom">
    <div class="uk-text-center">
        <img src="{{asset('vendor/totem/img/mask.svg')}}" class="uk-svg">
        <div class="uk-text-large">Totem</div>
    </div>
    <hr>
    <ul class="uk-nav uk-nav-default">
        <li class="{{ Str::contains(url()->current(), 'tasks') && !request()->routeIs('totem.upcoming') && !request()->routeIs('totem.upcoming.events') ? 'uk-active' : '' }}">
            <a href="{{route('totem.tasks.all')}}" class="uk-flex uk-flex-middle">
                <span uk-icon="icon: clock; ratio: 1" class="uk-visible@m uk-margin-small-right"></span>
                <span class="uk-vertical-align-middle">Tasks</span>
            </a>
        </li>
        <li class="{{ request()->routeIs('totem.upcoming') ? 'uk-active' : '' }}">
            <a href="{{route('totem.upcoming')}}" class="uk-flex uk-flex-middle">
                <span uk-icon="icon: calendar; ratio: 1" class="uk-visible@m uk-margin-small-right"></span>
                <span class="uk-vertical-align-middle">Upcoming</span>
            </a>
        </li>
    </ul>
    <hr>
</aside>

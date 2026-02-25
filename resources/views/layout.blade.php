<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            Totem
            @yield('page-title')
        </title>
        <link rel="stylesheet" type="text/css" href="{{ asset('/vendor/totem/css/app.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('/vendor/totem/css/components.css') }}">
        @stack('style')
    </head>
    <body>
        <main id="root">
            <div class="uk-container uk-section">
                <div class="uk-grid">
                    @include('totem::partials.sidebar')
                    <section class="uk-width-5-6@l">
                        @include('totem::partials.alerts')
                        @yield('main-panel-before')
                        <div class="uk-card uk-card-default">
                            <div class="uk-card-header">
                                @yield('title')
                            </div>
                            <div class="uk-card-body">
                                @yield('main-panel-content')
                            </div>
                            <div class="uk-card-footer">
                                @yield('main-panel-footer')
                            </div>
                        </div>
                        @yield('main-panel-after')
                        @yield('additional-panels')
                        <div class="uk-margin-top">
                            @include('totem::partials.footer')
                        </div>
                    </section>
                </div>
            </div>
        </main>
        <script src="{{ asset('/vendor/totem/js/app.js') }}"></script>
        @stack('scripts')
    </body>
</html>

<template>
    <div>
        <!-- Controls -->
        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
            <div>
                <button
                    @click="setDays(1)"
                    :class="['uk-button uk-button-small', days === 1 ? 'uk-button-primary' : 'uk-button-default']"
                >
                    1 Day
                </button>
                <button
                    @click="setDays(3)"
                    :class="['uk-button uk-button-small', days === 3 ? 'uk-button-primary' : 'uk-button-default']"
                >
                    3 Days
                </button>
            </div>
            <div>
                <button @click="prev" class="uk-button uk-button-default uk-button-small uk-margin-small-right">
                    <span uk-icon="icon: chevron-left"></span>
                </button>
                <button @click="resetToNow" class="uk-button uk-button-default uk-button-small uk-margin-small-right">
                    Today
                </button>
                <button @click="next" class="uk-button uk-button-default uk-button-small">
                    <span uk-icon="icon: chevron-right"></span>
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="uk-text-center uk-padding">
            <span uk-spinner="ratio: 2"></span>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="uk-alert-danger" uk-alert>
            <p>{{ error }}</p>
        </div>

        <!-- Calendar Grid -->
        <div v-if="!loading && !error" class="totem-calendar" :style="gridStyle">
            <!-- Header row: empty time-label cell + one day header per column -->
            <div class="totem-calendar__time-label totem-calendar__time-label--header"></div>
            <div
                v-for="day in dayColumns"
                :key="'header-' + day.key"
                class="totem-calendar__day-header"
            >
                {{ day.label }}
            </div>

            <!-- 24 hour rows -->
            <template v-for="hour in 24">
                <div :key="'label-' + hour" class="totem-calendar__time-label">
                    {{ formatHour(hour - 1) }}
                </div>
                <div
                    v-for="day in dayColumns"
                    :key="day.key + '-' + hour"
                    class="totem-calendar__cell"
                >
                    <div
                        v-for="group in groupedEventsForDayAndHour(day.date, hour - 1)"
                        :key="group.minute"
                        class="totem-calendar__minute-group"
                    >
                        <a
                            v-for="(event, idx) in group.events"
                            :key="event.task_id + '-' + event.scheduled_at + '-' + idx"
                            class="totem-calendar__event"
                            :style="{ background: commandColor(event.command) }"
                            :title="event.description + ' (' + event.command + ')'"
                            :href="taskBaseUrl.replace(/\/$/, '') + '/' + event.task_id"
                        >
                            <span class="totem-calendar__event-time">{{ formatTime(event.scheduled_at) }}</span>
                            <span class="totem-calendar__event-desc">{{ truncate(event.description) }}</span>
                        </a>
                    </div>
                </div>
            </template>
        </div>

    </div>
</template>

<script>
    import moment from 'moment';

    export default {
        props: {
            eventsUrl: {
                type: String,
                required: true,
            },
            taskBaseUrl: {
                type: String,
                required: true,
            },
        },

        data() {
            const params = new URLSearchParams(window.location.search);
            const daysParam = parseInt(params.get('days'), 10);
            const days = [1, 3].includes(daysParam) ? daysParam : 1;
            const startParam = params.get('start');
            const currentStart = startParam && moment(startParam).isValid()
                ? moment(startParam).startOf('day').toDate()
                : moment().startOf('day').toDate();
            return {
                days,
                currentStart,
                events: [],
                loading: false,
                error: null,
                fetchGen: 0,
            };
        },

        computed: {
            gridStyle() {
                return {
                    display: 'grid',
                    gridTemplateColumns: '80px repeat(' + this.days + ', 1fr)',
                };
            },

            dayColumns() {
                const cols = [];
                for (let i = 0; i < this.days; i++) {
                    const date = moment(this.currentStart).add(i, 'days');
                    cols.push({
                        key: date.format('YYYY-MM-DD'),
                        label: date.format('ddd, MMM D'),
                        date: date.format('YYYY-MM-DD'),
                    });
                }
                return cols;
            },
        },

        mounted() {
            this.fetchEvents();
        },

        methods: {
            setDays(n) {
                this.days = n;
                this.fetchEvents();
            },

            prev() {
                this.currentStart = moment(this.currentStart).subtract(this.days, 'days').toDate();
                this.fetchEvents();
            },

            next() {
                this.currentStart = moment(this.currentStart).add(this.days, 'days').toDate();
                this.fetchEvents();
            },

            resetToNow() {
                this.currentStart = moment().startOf('day').toDate();
                this.fetchEvents();
            },

            syncUrl() {
                const start = moment(this.currentStart).format('YYYY-MM-DD');
                const end = moment(this.currentStart).add(this.days, 'days').format('YYYY-MM-DD');
                const params = new URLSearchParams({ start, end, days: this.days });
                history.replaceState(null, '', '?' + params.toString());
            },

            fetchEvents() {
                this.syncUrl();
                const gen = ++this.fetchGen;
                this.loading = true;
                this.error = null;
                const start = moment(this.currentStart).format();

                axios.get(this.eventsUrl, { params: { start: start, days: this.days } })
                    .then(response => {
                        if (gen === this.fetchGen) {
                            this.events = response.data.events;
                        }
                    })
                    .catch(() => {
                        if (gen === this.fetchGen) {
                            this.error = 'Failed to load upcoming events. Please try again.';
                        }
                    })
                    .finally(() => {
                        if (gen === this.fetchGen) {
                            this.loading = false;
                        }
                    });
            },

            groupedEventsForDayAndHour(date, hour) {
                const groups = {};
                this.events.forEach(function (event) {
                    const m = moment(event.scheduled_at);
                    if (m.format('YYYY-MM-DD') !== date || m.hour() !== hour) return;
                    const minute = m.minute();
                    if (!groups[minute]) groups[minute] = [];
                    groups[minute].push(event);
                });
                return Object.keys(groups)
                    .sort(function (a, b) { return a - b; })
                    .map(function (minute) { return { minute: minute, events: groups[minute] }; });
            },

            formatHour(hour) {
                return moment().startOf('day').add(hour, 'hours').format('HH:mm');
            },

            formatTime(isoString) {
                return moment(isoString).format('HH:mm');
            },

            commandColor(command) {
                const palette = [
                    '#1d4ed8', // blue       6.70:1
                    '#0e7490', // cyan       5.36:1
                    '#0f766e', // teal       5.47:1
                    '#15803d', // green      5.02:1
                    '#065f46', // emerald    7.68:1
                    '#b45309', // amber      5.02:1
                    '#c2410c', // orange     5.18:1
                    '#b91c1c', // red        6.47:1
                    '#9f1239', // rose       8.02:1
                    '#be185d', // pink       6.04:1
                    '#7e22ce', // purple     6.98:1
                    '#4338ca', // indigo     7.90:1
                ];
                let hash = 0;
                const str = command || '';
                for (let i = 0; i < str.length; i++) {
                    hash = (hash << 5) - hash + str.charCodeAt(i);
                    hash |= 0;
                }
                return palette[Math.abs(hash) % palette.length];
            },

            truncate(text) {
                if (!text) return '';
                return text.length > 50 ? text.substring(0, 50) + '\u2026' : text;
            },
        },
    };
</script>

<style scoped>
    .totem-calendar {
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        overflow: auto;
    }

    .totem-calendar__time-label {
        padding: 4px 8px;
        font-size: 12px;
        color: #999;
        border-right: 1px solid #e5e5e5;
        border-bottom: 1px solid #e5e5e5;
        text-align: right;
        min-height: 60px;
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
    }

    .totem-calendar__time-label--header {
        min-height: auto;
        background: #f8f8f8;
    }

    .totem-calendar__day-header {
        padding: 8px;
        font-weight: bold;
        text-align: center;
        border-bottom: 2px solid #1e87f0;
        border-right: 1px solid #e5e5e5;
        background: #f8f8f8;
    }

    .totem-calendar__cell {
        min-height: 60px;
        padding: 2px;
        border-bottom: 1px solid #e5e5e5;
        border-right: 1px solid #e5e5e5;
        vertical-align: top;
    }

    .totem-calendar__minute-group {
        display: flex;
        flex-wrap: wrap;
        gap: 2px;
        margin-bottom: 2px;
    }

    .totem-calendar__event {
        color: white;
        text-decoration: none;
        border-radius: 3px;
        padding: 2px 4px;
        font-size: 11px;
        overflow: hidden;
        white-space: nowrap;
    }

    .totem-calendar__event-time {
        font-weight: bold;
        margin-right: 4px;
    }

    .totem-calendar__event-desc {
        opacity: 0.9;
    }

</style>

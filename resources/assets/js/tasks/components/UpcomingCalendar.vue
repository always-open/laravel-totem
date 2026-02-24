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
        <div v-if="error" class="uk-alert-danger" uk-alert>
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
                        v-for="(event, idx) in eventsForDayAndHour(day.date, hour - 1)"
                        :key="event.task_id + '-' + event.scheduled_at + '-' + idx"
                        class="totem-calendar__event"
                        :title="event.description + ' (' + event.command + ')'"
                    >
                        <span class="totem-calendar__event-time">{{ formatTime(event.scheduled_at) }}</span>
                        <span class="totem-calendar__event-desc">{{ truncate(event.description) }}</span>
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
        },

        data() {
            return {
                days: 1,
                currentStart: moment().startOf('hour').toDate(),
                events: [],
                loading: false,
                error: null,
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
                this.currentStart = moment().startOf('hour').toDate();
                this.fetchEvents();
            },

            fetchEvents() {
                this.loading = true;
                this.error = null;
                const start = moment(this.currentStart).toISOString();

                axios.get(this.eventsUrl, { params: { start: start, days: this.days } })
                    .then(response => {
                        this.events = response.data.events;
                    })
                    .catch(() => {
                        this.error = 'Failed to load upcoming events. Please try again.';
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            eventsForDayAndHour(date, hour) {
                return this.events.filter(function (event) {
                    const m = moment(event.scheduled_at);
                    return m.format('YYYY-MM-DD') === date && m.hour() === hour;
                });
            },

            formatHour(hour) {
                return moment().startOf('day').add(hour, 'hours').format('HH:mm');
            },

            formatTime(isoString) {
                return moment(isoString).format('HH:mm');
            },

            truncate(text) {
                return text.length > 20 ? text.substring(0, 20) + '\u2026' : text;
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

    .totem-calendar__event {
        background: #1e87f0;
        color: white;
        border-radius: 3px;
        padding: 2px 4px;
        margin-bottom: 2px;
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

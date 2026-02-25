import dayjs from 'dayjs';

export function useFormatDate() {
    function formatDate(unixTime) {
        return dayjs(unixTime * 1000).add(new Date().getTimezoneOffset() / 60, 'hour');
    }

    function readableTimestamp(timestamp) {
        return formatDate(timestamp).format('HH:mm:ss');
    }

    return { formatDate, readableTimestamp };
}

<script>
import { h, onMounted, onUnmounted, useSlots } from 'vue';

export default {
    name: 'ClickToClose',
    props: {
        do: {
            type: Function,
            required: true,
        },
    },
    setup(props) {
        const slots = useSlots();

        const listener = (e) => {
            const el = document.querySelector('[data-click-to-close]');
            if (!el || el === e.target || el.contains(e.target)) return;
            props.do();
        };

        onMounted(() => document.addEventListener('click', listener));
        onUnmounted(() => document.removeEventListener('click', listener));

        return () => {
            const defaultSlot = slots.default?.();
            if (!defaultSlot || !defaultSlot.length) return null;
            const child = defaultSlot[0];
            if (child.props) {
                child.props['data-click-to-close'] = true;
            }
            return child;
        };
    },
};
</script>

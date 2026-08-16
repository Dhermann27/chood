import {onBeforeUnmount, onMounted, ref} from 'vue';
import axios from 'axios';

export function useMapPolling(url, intervalMs, onData) {
    const localChecksum = ref('');
    let intervalId = null;

    async function poll() {
        try {
            const response = await axios.get(url + localChecksum.value);
            if (response.data && localChecksum.value !== response.data?.checksum) {
                localChecksum.value = response.data.checksum;
                onData(response.data);
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }

    function restart() {
        localChecksum.value = '';
        clearInterval(intervalId);
        poll();
        intervalId = setInterval(poll, intervalMs);
    }

    onMounted(() => {
        poll();
        intervalId = setInterval(poll, intervalMs);
    });

    onBeforeUnmount(() => clearInterval(intervalId));

    return {localChecksum, poll, restart};
}

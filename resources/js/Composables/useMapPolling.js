import {onBeforeUnmount, onMounted, ref} from 'vue';
import axios from 'axios';

export function useMapPolling(url, intervalMs, onData) {
    const localChecksum = ref('');
    let intervalId = null;
    let isFetching = false;

    async function poll() {
        if (isFetching) return;
        isFetching = true;
        try {
            const response = await axios.get(url + localChecksum.value);
            if (response.data && localChecksum.value !== response.data?.checksum) {
                localChecksum.value = response.data.checksum;
                onData(response.data);
            }
        } catch (error) {
            console.error('Polling error:', error);
        } finally {
            isFetching = false;
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

import {onMounted, ref} from 'vue';
import {ControlSchemes} from '@/controlSchemes.js';

export function useControlScheme() {
    const controls = ref(ControlSchemes.NONE);

    onMounted(() => {
        if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
            controls.value = !navigator.userAgent.includes('Linux') ? ControlSchemes.MODAL : ControlSchemes.NONE;
        }
    });

    return {controls};
}

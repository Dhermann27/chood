<script setup>
import {ref, watch, onMounted, nextTick} from 'vue';
import {getFittedFontSize} from '@/utils.js';

const props = defineProps({
    sectionCounts: {type: Object, default: () => ({checkin_today: null, checkout_today: null})},
    fontSize: {type: Number, required: true},
    maxWidth: {type: Number, default: null},
});

const spanRef = ref(null);
const fittedSize = ref(props.fontSize + 'px');

async function fit() {
    fittedSize.value = props.fontSize + 'px';
    if (!spanRef.value || !props.maxWidth) return;
    await nextTick();
    fittedSize.value = getFittedFontSize(spanRef.value, props.maxWidth);
}

onMounted(fit);
watch(() => [props.sectionCounts, props.fontSize, props.maxWidth], fit, {deep: true});
</script>

<template>
    <span v-if="sectionCounts?.checkin_today !== null" ref="spanRef"
          class="flex items-center gap-1 leading-none font-bold"
          :style="{ fontSize: fittedSize }">
        <template v-if="sectionCounts.in_house != null">{{ sectionCounts.in_house }}</template>
        {{ sectionCounts.checkin_today }}
        <FontAwesomeIcon :icon="['fas', 'left-right']"/>
        {{ sectionCounts.checkout_today }}
    </span>
</template>

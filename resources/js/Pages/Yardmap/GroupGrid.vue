<script setup>
import {computed} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";
import SectionCounts from "@/Components/chood/SectionCounts.vue";
import {checkoutReservationColor} from '@/utils.js';

function checkoutStyle(dog) {
    if (!dog.checked_out_at) return {};
    return {outline: `9px dashed ${checkoutReservationColor(dog)}`};
}

const props = defineProps({
    groupKey: {type: [String, Number], required: true},
    dogsByGroup: {type: Object, required: true},
    rowsByGroup: {type: Object, required: true},
    colsByGroup: {type: Object, required: true},
    cardWidth: {type: Number, required: true},
    cardHeight: {type: Number, required: true},
    sectionCounts: {type: Object, default: () => ({checkin_today: null, checkout_today: null})},
});

const dogs = computed(() => props.dogsByGroup?.[props.groupKey] ?? []);
const activeCount = computed(() => {
    return dogs.value.filter(d => !d.rest_starts_at && !d.checked_out_at).length;
});
const lsActiveCount = computed(() => {
    return dogs.value.filter(d => d.size_letter === 'LS' && !d.rest_starts_at && !d.checked_out_at).length;
});
const gridStyle = computed(() => {
    const rows = props.rowsByGroup?.[props.groupKey] ?? 1;
    const cols = props.colsByGroup?.[props.groupKey] ?? 1;

    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${cols}, ${props.cardWidth}px)`,
        gridTemplateRows: `repeat(${rows}, ${props.cardHeight}px)`,
        gap: `10px`,
        justifyItems: 'start',
        alignItems: 'start',
        alignContent: 'start',
        height: '100%',
    };
});
</script>

<template>
    <div class="h-full overflow-y-auto overflow-x-hidden min-w-0">
        <div class="p-1 w-full h-full overflow-x-hidden min-w-0" :style="gridStyle">
            <div v-for="(dog, dogIndex) in dogs" :key="dog.id ?? `${groupKey}-${dogIndex}`" class="w-full h-full"
                 :style="checkoutStyle(dog)">
                <DogCard :dogs="[dog]" :card-width="cardWidth" :card-height="cardHeight"/>
            </div>

            <div class="w-full h-full flex relative items-center justify-center bg-crimson text-white font-bold">
                <span :style="{ fontSize: (cardHeight * 0.5) + 'px' }">
                    {{ activeCount }}
                </span>
                <span v-if="lsActiveCount > 0" class="absolute bottom-2 right-1 leading-none"
                      :style="{ fontSize: (cardHeight * 0.2) + 'px', bottom: '5px', right: '5px' }">
                    LS:{{ lsActiveCount }}
                </span>
                <div class="absolute top-2 left-0 right-0 flex items-center justify-center">
                    <SectionCounts :section-counts="sectionCounts" :font-size="cardHeight * 0.18"
                                   :max-width="cardWidth"/>
                </div>
            </div>
        </div>
    </div>
</template>

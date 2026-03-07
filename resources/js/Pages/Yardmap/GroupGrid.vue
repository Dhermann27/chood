<script setup>
import {computed} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    groupKey: {type: [String, Number], required: true},
    dogsByGroup: {type: Object, required: true},
    rowsByGroup: {type: Object, required: true},
    colsByGroup: {type: Object, required: true},
    cardWidth: {type: Number, required: true},
    cardHeight: {type: Number, required: true},
    photoUri: {type: String, default: ''},

    dogIndexById: {type: Object, required: true},
    currentLoadingIndex: {type: Number, required: true},
});

const emit = defineEmits(['imageLoaded']);

const dogs = computed(() => props.dogsByGroup?.[props.groupKey] ?? []);
const activeCount = computed(() => {
    return dogs.value.length - dogs.value.filter(d => d.rest_starts_at !== null).length;
});
const lsActiveCount = computed(() => {
    return dogs.value.filter(d => d.size_letter === 'LS' && d.rest_starts_at === null).length;
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
            <div v-for="(dog, dogIndex) in dogs" :key="dog.id ?? `${groupKey}-${dogIndex}`" class="w-full h-full">
                <DogCard :dogs="[dog]" :photoUri="photoUri" :card-width="cardWidth" :card-height="cardHeight"
                         :shouldLoad="dogIndexById?.[dog.id] === currentLoadingIndex"
                         @imageLoaded="emit('imageLoaded')"/>
            </div>

            <div class="w-full h-full flex relative items-center justify-center bg-crimson text-white font-bold">
                <span :style="{ fontSize: (cardHeight * 0.5) + 'px' }">
                    {{ activeCount }}
                </span>
                <span v-if="lsActiveCount > 0" class="absolute flex items-center justify-center p-5"
                    :style="{ fontSize: (cardHeight * 0.2) + 'px', bottom: '5px', right: '5px' }">
                    {{ lsActiveCount }}
                </span>
            </div>
        </div>
    </div>
</template>

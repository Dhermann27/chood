<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, nextTick, onBeforeUnmount, onMounted, ref} from 'vue';
import {formatTime, getFittedFontSize} from "@/utils.js";
import GroupGrid from './GroupGrid.vue';

const DIVIDER_W = 40;

const props = defineProps({
    size: String,
    photoUri: String,
    yardOrder: Object,
});
const dogsByGroup = ref({});
const assignments = ref([]);
const nextBreak = ref(null);
const nextLunch = ref(null);
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
const localChecksum = ref('');
const chyron = ref(null);
const chyronFontSize = ref('60px');
let refreshIntervals = [];

const chyronStyle = computed(() => ({
    height: '100px',
    textAlign: 'center',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: chyronFontSize.value,
    gridColumn: '1 / -1',
    backgroundColor: '#9e1b32',
    color: 'white',
}));

const allDogs = computed(() => {
    return Object.values(dogsByGroup.value ?? {}).flat();
});
const groupKeys = computed(() => Object.keys(dogsByGroup.value ?? {}).sort((a, b) =>
    (props.yardOrder[a] ?? 99) - (props.yardOrder[b] ?? 99)
));
const colsByGroup = computed(() => {
    const out = {};
    Object.entries(dogsByGroup.value ?? {}).forEach(([key, dogs]) => {
        const count = (dogs?.length ?? 0) + 1; // + summary tile
        out[key] = Math.max(1, Math.ceil(Math.sqrt((16 / 9) * count)));
    });
    return out;
});
const rowsByGroup = computed(() => {
    const out = {};
    Object.entries(dogsByGroup.value ?? {}).forEach(([key, dogs]) => {
        const count = (dogs?.length ?? 0) + 1;
        const cols = colsByGroup.value[key] ?? 1;
        out[key] = Math.max(1, Math.ceil(count / cols));
    });
    return out;
});
const maxRows = computed(() => {
    const vals = Object.values(rowsByGroup.value);
    return vals.length ? Math.max(...vals) : 1;
});
const cardWidth = computed(() => {
    const cols = groupKeys.value.map(k => colsByGroup.value[k] ?? 1);
    const totalCols = Math.max(1, cols.reduce((sum, c) => sum + (c ?? 0), 0));
    const internalGapsPx = (groupKeys.value.length >= 2 ? ((cols[0] ?? 1) - 1) + ((cols[1] ?? 1) - 1) : ((cols[0] ?? 1) - 1)) * 10;
    return Math.floor((1920 - (groupKeys.value.length >= 2 ? DIVIDER_W : 0) - Math.max(0, internalGapsPx)) / totalCols);
});

const leftWidth = computed(() => {
    const cols = groupKeys.value.map(k => colsByGroup.value[k] ?? 1);
    const leftCols = cols[0] ?? 1;
    return (leftCols * cardWidth.value) + (Math.max(0, leftCols - 1) * 10);
});
const rightWidth = computed(() => {
    const cols = groupKeys.value.map(k => colsByGroup.value[k] ?? 1);
    const rightCols = cols[1] ?? 0;
    return groupKeys.value.length >= 2 ? (rightCols * cardWidth.value) + (Math.max(0, rightCols - 1) * 10) : 0;
});
const cardHeight = computed(() => {
    return ((1080 - 100) - (maxRows.value - 1) * 10) / maxRows.value;
});

function getNewGifAndPosition() {
    return {
        newGif: '/images/doggifs/dog' + (Math.floor(Math.random() * 11) + 1) + '.webp',
        top: Math.random() * (1080 - 480),
        left: Math.random() * (1920 - 480),
    };
}

async function updateData() {

    try {
        const response = await axios.get(`/api/yardmap${props.size}/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data?.checksum) {
            dogsByGroup.value = response.data.dogs;
            assignments.value = response.data.assignments;
            nextBreak.value = response.data.nextBreak;
            nextLunch.value = response.data.nextLunch;
            localChecksum.value = response.data.checksum;

            if (chyron.value) {
                await nextTick();
                chyronFontSize.value = getFittedFontSize(chyron.value, 1920);
            }
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

async function updateGif() {
    if (allDogs.value.length === 0) {
        const {newGif: fetchedGif, left: fetchedLeft, top: fetchedTop} = getNewGifAndPosition();
        currentGif.value = fetchedGif;
        randomPosition.value = {top: fetchedTop, left: fetchedLeft};
    }
}

onMounted(() => {
    updateData();
    refreshIntervals[0] = setInterval(updateData, 5000);
    refreshIntervals[1] = setInterval(updateGif, 60000);
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    refreshIntervals.forEach(intervalId => clearInterval(intervalId));
});
</script>

<template>
    <Head title="Yardmap"/>
    <main class="w-full h-full grid" style="grid-template-rows: 1fr 100px;">
        <div
            class="w-full h-full min-w-0 overflow-x-hidden"
            :style="{display: 'grid',
            gridTemplateColumns: groupKeys.length >= 2 ? `${leftWidth}px ${DIVIDER_W}px ${rightWidth}px` : '1fr',}">

            <div v-if="groupKeys.length >= 1" class="min-w-0 overflow-hidden">
                <GroupGrid :groupKey="groupKeys[0]" :dogsByGroup="dogsByGroup"
                           :rowsByGroup="rowsByGroup" :colsByGroup="colsByGroup" :cardWidth="cardWidth"
                           :cardHeight="cardHeight"/>
            </div>

            <div v-if="groupKeys.length >= 2" class="bg-crimson h-full" :style="{width: DIVIDER_W + 'px'}"></div>

            <div v-if="groupKeys.length >= 2" class="min-w-0 overflow-hidden">
                <GroupGrid :groupKey="groupKeys[1]" :dogsByGroup="dogsByGroup"
                           :rowsByGroup="rowsByGroup" :colsByGroup="colsByGroup" :cardWidth="cardWidth"
                           :cardHeight="cardHeight"/>
            </div>

        </div>

        <img v-if="allDogs.length === 0" :src="currentGif" alt="Dancing Doggo"
             :style="{ top: randomPosition.top + 'px', left: randomPosition.left + 'px', position: 'absolute' }"/>
        <div ref="chyron" :style="chyronStyle">
                <span v-for="assignment in assignments" class="pe-8 whitespace-nowrap">
                    {{ assignment.yard?.name }}:
                    {{ assignment.employee?.first_name ?? 'None' }}
                </span>
            <span v-if="nextBreak" class="pe-8 whitespace-nowrap">
                    Break: {{ nextBreak.employee.first_name }}
                    {{ formatTime(nextBreak.next_break) }}
                </span>
            <span v-if="nextLunch" class="whitespace-nowrap">
                    Lunch: {{ nextLunch.employee.first_name }}
                    {{ formatTime(nextLunch.next_lunch_break) }}
                </span>
        </div>
    </main>
</template>

<style>
.icon-with-outline, .minutes-remaining {
    filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.7)) drop-shadow(0 0 8px rgba(0, 0, 0, 0.7));
}
</style>


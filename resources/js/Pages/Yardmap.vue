<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount, nextTick} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";
import {formatTime, scaleObjects} from "@/utils.js";

const props = defineProps({
    size: String,
    photoUri: String
});
const dogs = ref([]);
const assignments = ref([]);
const nextBreak = ref(null);
const nextLunch = ref(null);
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
const localChecksum = ref('');
let refreshIntervals = [];
const columns = computed(() => Math.ceil(Math.sqrt((16 / 9) * dogs.value.length)));
const rows = computed(() => Math.ceil(dogs.value.length / columns.value));
const yardGridStyle = computed(() => getYardGridStyle(rows.value, columns.value));
const cardWidth = computed(() => (1918 - (columns.value - 1) * 10) / columns.value);
const cardHeight = computed(() => (978 - (rows.value - 1) * 10) / rows.value);
const chyronStyle = computed(() => ({
    height: '100px',
    textAlign: 'center',
    display: 'flex',
    alignItems: 'center',       // Vertically aligns text
    justifyContent: 'center',   // Horizontally aligns text
    fontSize: '60px',
    gridColumn: '1 / -1',
    backgroundColor: '#9e1b32',
    color: 'white'
}));

function getYardGridStyle(rows, columns) {
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gridTemplateRows: `repeat(${rows}, 1fr) 100px`,
        gap: '10px',
    };
}

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
            dogs.value = response.data.dogs;
            assignments.value = response.data.assignments;
            nextBreak.value = response.data.nextBreak;
            nextLunch.value = response.data.nextLunch;
            localChecksum.value = response.data.checksum;
            await nextTick(() => {
                scaleObjects();
            });
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

async function updateGif() {
    if (dogs.value.length === 0) {
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
    <main class="w-full h-full">
        <div id="yardmap" class="items-center justify-center p-1" :style="yardGridStyle">
            <div v-for="dog in dogs" :style="{height: cardHeight + 'px', width: cardWidth + 'px'}">
                <DogCard :dogs="[dog]" :photoUri="props.photoUri" :card-width="cardWidth" :card-height="cardHeight"/>
            </div>
            <img v-if="dogs.length === 0" :src="currentGif" alt="Dancing Doggo"
                 :style="{ top: randomPosition.top + 'px', left: randomPosition.left + 'px', position: 'absolute' }"
            />
            <div id="chyron" :style="chyronStyle">
                <span v-for="assignment in assignments" class="pe-10">
                    {{ assignment.yard_number === 1 ? 'Small' : 'Large' }}:
                    {{ assignment.employee?.first_name ?? 'None' }}
                </span>
                <span v-if="nextBreak" class="pe-10">
                    Next Break: {{ nextBreak.first_name }} @
                    {{ formatTime(nextBreak.next_break) }}
                </span>
                <span v-if="nextLunch" class="pe-10">
                    Next Lunch: {{ nextLunch.first_name }} @
                    {{ formatTime(nextLunch.next_lunch_break) }}
                </span>
            </div>
        </div>
    </main>
</template>

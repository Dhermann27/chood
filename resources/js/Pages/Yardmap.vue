<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount, nextTick} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";
import {fetchMapData, getNewGifAndPosition, getYardGridStyle, scaleObjects} from "@/utils.js";

const props = defineProps({
    size: String,
    photoUri: String
});
const dogs = ref([]);
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
const localChecksum = ref('');
let refreshIntervals = [];
const columns = computed(() => Math.ceil(Math.sqrt((16 / 9) * dogs.value.length)));
const rows = computed(() => Math.ceil(dogs.value.length / columns.value));
const yardGridStyle = computed(() => getYardGridStyle(rows.value, columns.value));
const cardWidth = computed(() => (1918 - (columns.value - 1) * 10) / columns.value);
const cardHeight = computed(() => (1078 - (rows.value - 1) * 10) / rows.value);

async function updateData() {
    const {
        dogs: fetchedDogs,
        checksum: fetchedChecksum
    } = await fetchMapData(`/api/yardmap${props.size}/`, localChecksum.value);
    if (localChecksum.value !== fetchedChecksum) {
        dogs.value = fetchedDogs;
        localChecksum.value = fetchedChecksum;
        await nextTick(() => {
            scaleObjects();
        });
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
        </div>
    </main>
</template>

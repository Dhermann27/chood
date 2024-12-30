<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    photoUri: String,
    dogs: Object,
    checksum: String
});
const dogs = ref(props.dogs);
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
let local_checksum = ref(props.checksum);
let refreshInterval = null;
const columns = computed(() => Math.ceil(Math.sqrt((16 / 9) * dogs.value.length)));
const rows = computed(() => Math.ceil(dogs.value.length / columns.value));
const cardWidth = computed(() => (1920 - (columns.value - 1) * 10) / columns.value);
const cardHeight = computed(() => (1080 - (rows.value - 1) * 10) / rows.value);


const fetchData = async () => {
    try {
        const response = await fetch('/api/yardmaplarge/' + local_checksum.value);
        const newData = await response.json();
        if (newData) {
            local_checksum.value = newData.checksum;
            dogs.value = newData.dogs;
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

const gridStyle = computed(() => {
    return {
        display: 'grid',
        'grid-template-columns': `repeat(${columns.value}, ${cardWidth.value}px)`,
        'grid-template-rows': `repeat(${rows.value}, ${cardHeight.value}px)`,
        gap: '10px',
    };
});


const changeGifAndPosition = () => {
    // Select a random GIF
    const randomGifIndex = Math.floor(Math.random() * 11) + 1;
    currentGif.value = '/images/doggifs/dog' + randomGifIndex + '.webp';

    // Get the full screen container's dimensions
    const container = document.querySelector('#yardmap');
    const containerWidth = container.clientWidth > 0 ? container.clientWidth : container.offsetWidth;
    const containerHeight = container.clientHeight > 0 ? container.clientHeight : container.offsetHeight;

    // Generate random positions within the container
    randomPosition.value = {
        top: Math.random() * (containerHeight - 480),
        left: Math.random() * (containerWidth - 480),
    };
};

onMounted(() => {
    refreshInterval = setInterval(fetchData, 5000); // Refresh data every 5 seconds
    setInterval(changeGifAndPosition, 60000);
    changeGifAndPosition();
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});

</script>

<template>
    <Head title="Yardmap Large"/>
    <div class="w-full max-w-full">
        <main>
            <div id="yardmap" class="w-1920 h-1080 items-center justify-center" :style="gridStyle">
                <div v-for="dog in dogs" :style="{height: cardHeight + 'px', width: cardWidth + 'px'}">
                    <DogCard :dog="dog" :photoUri="props.photoUri" :card-width="cardWidth" :card-height="cardHeight"/>
                </div>
                <div v-if="dogs.length === 0">
                    <img :src="currentGif" alt="Dancing Doggo"
                         :style="{ top: randomPosition.top + 'px', left: randomPosition.left + 'px', position: 'absolute' }"
                    />
                </div>
            </div>
        </main>
    </div>
</template>

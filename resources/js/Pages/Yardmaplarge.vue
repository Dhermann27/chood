<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    photoUri: {
        type: String,
    },
    dogs: {
        type: Object,
    },
    checksum: {
        type: String,
    }
});

const dogs = ref({});
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
let local_checksum = ref(props.checksum);
let refreshInterval = null;

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
    const columns = Math.ceil(Math.sqrt((16 / 9) * dogs.value.length));  // Example for 16:9 aspect ratio
    const rows = Math.ceil(dogs.value.length / columns);

    return {
        display: 'grid',
        'grid-template-columns': `repeat(${columns}, 1fr)`,
        'grid-template-rows': `repeat(${rows}, 1fr)`,
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


// Fetch data when the component is mounted
onMounted(() => {
    dogs.value = props.dogs;
    refreshInterval = setInterval(fetchData, 5000); // Refresh data every 5 seconds
    setInterval(changeGifAndPosition, 60000);
    changeGifAndPosition();
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Yardmap Large"/>
    <div class="bg-gray-90 text-black/50 ">
        <div
            class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
            <div class="relative w-full px-6 max-w-full">
                <main>
                    <div id="yardmap" class="w-full h-screen" :style="gridStyle">
                        <div v-for="dog in dogs">
                            <DogCard :dog="dog" :photoUri="props.photoUri"/>
                        </div>
                        <div v-if="dogs.length === 0">
                            <img :src="currentGif" alt="Dancing Doggo"
                                 :style="{ top: randomPosition.top + 'px', left: randomPosition.left + 'px', position: 'absolute' }"
                            />
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

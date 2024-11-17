<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
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

const isBoarder = (services) => {
    if (services) return services.some(service => service.id === 1003 || service.id === 1004);
    return false;
}

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


// Fetch data when the component is mounted
onMounted(() => {
    dogs.value = props.dogs;
    refreshInterval = setInterval(fetchData, 5000); // Refresh data every 5 seconds
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
                    <div class="w-full h-screen" :style="gridStyle">
                        <div v-for="dog in dogs">
                            <DogCard :dog="dog" :photoUri="props.photoUri"/>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style>
.dog-card {
    position: relative;
    font-size: calc(1rem + 1vw);
    background-size: cover;
    background-position: center;
    color: #44687d;
    overflow: hidden;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.7);
}

.dog-banner {
    font-size: calc(1rem + 1vw);
}

.boarder-banner, .daycamper-banner {
    min-height: 1.5em;
    line-height: 1.5em;
}

.choodmap > div {
    border-width: 5px;
}
</style>

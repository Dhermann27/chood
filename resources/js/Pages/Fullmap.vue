<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import textFit from "textfit";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    services: Array,
    dogs: Object,
    outhouseDogs: Array,
    checksum: String
});

const dogs = ref({});
let local_checksum = ref(props.checksum);
let refreshInterval = null;

const fetchData = async () => {
    try {
        const response = await fetch('/api/fullmap/' + local_checksum.value);
        const newData = await response.json();
        if (newData) {
            local_checksum.value = newData.checksum;
            dogs.value = newData.dogs;
        }
        textFit(document.querySelectorAll('.dog-name'), {alignVert: true, alignHoriz: true});
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

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
    <Head title="Fullmap"/>
    <div class="bg-gray-90 text-black/50 ">
        <div
            class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
            <div class="relative w-full px-6 max-w-full">
                <main>
                    <div class="w-full h-screen choodmap">
                        <Map :cabins="cabins" :dogs="dogs" :outhouse-dogs="outhouseDogs" :services="services" :photoUri="photoUri" :maxlength="8"/>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: 1fr 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}
</style>

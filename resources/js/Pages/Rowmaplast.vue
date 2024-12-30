<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    dogs: Object,
    checksum: String
});
const dogs = ref(props.dogs);
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
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

// Fetch data when the component is mounted
onMounted(() => {
    refreshInterval = setInterval(fetchData, 5000); // Refresh data every 5 seconds
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Rowmap Lastrow"/>
    <div class="w-full max-w-full">
        <main>
            <div class="w-1080 h-1920 choodmap items-center justify-center">
                <Map :cabins="cabins" :dogs="dogs" :photoUri="photoUri" :maxlength="12"
                     :card-width="188" :card-height="200"/>
            </div>
        </main>
    </div>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: repeat(2, 188px 188px 40px) 188px;
    grid-template-rows: repeat(4, 200px) 20px repeat(5, 200px);
}
</style>

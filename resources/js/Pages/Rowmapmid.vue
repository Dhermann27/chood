<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {fetchData} from "@/utils.js";

const props = defineProps({
    photoUri: String,
    cabins: Array
});
const dogs = ref([]);
const localChecksum = ref('');
let refreshInterval;

async function updateData() {
    const {
        dogs: fetchedDogs,
        checksum: fetchedChecksum
    } = await fetchData(`/api/fullmap/`, localChecksum.value);
    if (localChecksum.value !== fetchedChecksum) {
        dogs.value = fetchedDogs;
        localChecksum.value = fetchedChecksum;
    }
}

// Fetch data when the component is mounted
onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, 5000);
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Rowmap Midrow"/>
    <main class="w-full h-full">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :dogs="dogs" :photoUri="photoUri" :maxlength="12"
                 :card-width="260" :card-height="211"/>
        </div>
    </main>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: repeat(2, 1fr) 40px repeat(2, 1fr);
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}
</style>

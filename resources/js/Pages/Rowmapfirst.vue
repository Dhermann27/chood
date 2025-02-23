<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {fetchMapData} from "@/utils.js";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    dogs: Object,
    checksum: String
});
const dogs = ref([]);
const statuses = ref([]);
const localChecksum = ref('');
let refreshInterval;

async function updateData() {
    const response = await fetchMapData(`/api/fullmap/`, localChecksum.value);

    if (response && localChecksum.value !== response.checksum) {
        dogs.value = response.dogs;
        statuses.value = response.statuses;
        localChecksum.value = response.checksum;
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
    <Head title="Rowmap Firstrow"/>
    <main class="w-full h-full">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :photoUri="photoUri" :maxlength="12"
                 :card-width="204" :card-height="216"/>
        </div>
    </main>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: 1fr repeat(3, 20px 1fr 1fr) 20px 1fr;
    grid-template-rows: repeat(5, 1fr);
}
</style>

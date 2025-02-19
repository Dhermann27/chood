<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {fetchMapData} from "@/utils.js";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    services: Array
});
const dogs = ref([]);
const statuses = ref({});
const outhouseDogs = ref([]);
const localChecksum = ref('');
const admin = ref(0 );
let refreshInterval;

async function updateData() {
    const response = await fetchMapData(`/api/fullmap/`, localChecksum.value);

    if (response && localChecksum.value !== response.checksum) {
        dogs.value = response.dogs;
        statuses.value = response.statuses;
        outhouseDogs.value = response.outhouseDogs;
        localChecksum.value = response.checksum;
    }
}

// Fetch data when the component is mounted
onMounted(() => {
    if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
        admin.value = !navigator.userAgent.includes('Linux') ? 2 : 0;
    }

    updateData();
    refreshInterval = setInterval(updateData, 5000);
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Fullmap"/>
    <main class="w-full h-full">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :outhouse-dogs="outhouseDogs" :services="services"
                 :photoUri="photoUri" :admin="admin" :maxlength="8" :card-width="96" :card-height="117"/>
        </div>
    </main>
</template>

<style>
.choodmap {
    display: grid;
    text-align: center;
    grid-template-columns: 1fr repeat(8, 20px 1fr 1fr) 20px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}
</style>

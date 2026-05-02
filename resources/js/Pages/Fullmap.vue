<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {fetchMapData} from "@/utils.js";
import {ControlSchemes} from "@/controlSchemes.js";

const props = defineProps({
    cabins: Array
});
const dogs = ref([]);
const statuses = ref({});
const localChecksum = ref('');
const controls = ref(ControlSchemes.NONE);
const sectionCounts = ref({checkin_today: null, checkout_today: null});
let refreshInterval;

async function updateData() {
    const response = await fetchMapData(`/api/fullmap/`, localChecksum.value);

    if (response && localChecksum.value !== response.checksum) {
        dogs.value = response.dogs;
        statuses.value = response.statuses;
        sectionCounts.value = response.sectionCounts ?? sectionCounts.value;
        localChecksum.value = response.checksum;
    }
}

// Fetch data when the component is mounted
onMounted(() => {
    if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
        controls.value = !navigator.userAgent.includes('Linux') ? ControlSchemes.MODAL : ControlSchemes.NONE;
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
    <main class="w-full h-full relative">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :controls="controls"
                 :maxlength="8" :card-width="96" :card-height="117"
                 :display-cabin-id="2028" :section-counts="sectionCounts"/>
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

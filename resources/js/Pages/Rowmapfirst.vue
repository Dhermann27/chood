<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {fetchMapData} from "@/utils.js";

const props = defineProps({
    cabins: Array,
    dogs: Object,
    checksum: String
});
const dogs = ref([]);
const statuses = ref([]);
const localChecksum = ref('');
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

onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, 5000);
});

onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Rowmap Firstrow"/>
    <main class="w-full h-full relative">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :maxlength="12"
                 :card-width="230" :card-height="216"/>
        </div>
        <div v-if="sectionCounts.in_house != null"
             class="bg-crimson text-white font-bold flex flex-col items-center justify-center"
             style="position: absolute; top: 4px; left: 4px; width: 230px; height: 216px;">
            <span v-if="sectionCounts.checkin_today !== null"
                  class="flex items-center justify-center gap-1 leading-none"
                  style="font-size: 39px;">
                {{ sectionCounts.checkin_today }}
                <FontAwesomeIcon :icon="['fas', 'arrows-left-right']" style="transform: translateY(-0.1em)"/>
                {{ sectionCounts.checkout_today }}
            </span>
            <span style="font-size: 108px; line-height: 1;">{{ sectionCounts.in_house }}</span>
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

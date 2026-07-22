<script setup>
import {Head} from '@inertiajs/vue3';
import {ref} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {useMapPolling} from "@/composables/useMapPolling.js";

const props = defineProps({
    cabins: Array,
});
const dogs = ref([]);
const statuses = ref([]);
const sectionCounts = ref({checkin_today: null, checkout_today: null});

useMapPolling('/api/fullmap/', 5000, (data) => {
    dogs.value = data.dogs;
    statuses.value = data.statuses;
    sectionCounts.value = data.sectionCounts ?? sectionCounts.value;
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
                <FontAwesomeIcon :icon="['fas', 'left-right']"/>
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

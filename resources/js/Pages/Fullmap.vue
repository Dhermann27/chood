<script setup>
import {Head} from '@inertiajs/vue3';
import {ref} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {useMapPolling} from "@/Composables/useMapPolling.js";
import {useControlScheme} from "@/Composables/useControlScheme.js";

const props = defineProps({
    cabins: Array
});
const dogs = ref([]);
const statuses = ref({});
const sectionCounts = ref({checkin_today: null, checkout_today: null});
const {controls} = useControlScheme();

useMapPolling('/api/fullmap/', 5000, (data) => {
    dogs.value = data.dogs;
    statuses.value = data.statuses;
    sectionCounts.value = data.sectionCounts ?? sectionCounts.value;
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

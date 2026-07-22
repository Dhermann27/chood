<script setup>
import {Head} from '@inertiajs/vue3';
import {ref} from 'vue';
import Map from "@/Components/chood/Map.vue";
import {useMapPolling} from "@/composables/useMapPolling.js";

const props = defineProps({
    cabins: Array
});
const dogs = ref([]);
const statuses = ref([]);
const sectionCounts = ref({checkin_today: null, checkout_today: null});

useMapPolling('/api/fullmap/', 5000, (data) => {
    dogs.value = Object.fromEntries(
        Object.entries(data.dogs).filter(([cabinId]) =>
            new Set(props.cabins.map(c => c.id)).has(Number(cabinId))
        )
    );
    statuses.value = data.statuses;
    sectionCounts.value = data.sectionCounts ?? sectionCounts.value;
});
</script>

<template>
    <Head title="Rowmap Midrow"/>
    <main class="w-full h-full relative">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :maxlength="12"
                 :card-width="250" :card-height="211"
                 :display-cabin-id="2028" :section-counts="sectionCounts"/>
        </div>
    </main>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: 1fr 40px 1fr 1fr 40px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}
</style>

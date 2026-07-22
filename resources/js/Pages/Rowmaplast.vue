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

useMapPolling('/api/fullmap/', 5000, (data) => {
    dogs.value = Object.fromEntries(
        Object.entries(data.dogs).filter(([cabinId]) =>
            new Set(props.cabins.map(c => c.id)).has(Number(cabinId))
        )
    );
    statuses.value = data.statuses;
});
</script>

<template>
    <Head title="Rowmap Lastrow"/>
    <main class="w-full h-full">
        <div class="choodmap items-center justify-center p-1">
            <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :maxlength="12"
                 :card-width="160" :card-height="210"/>
        </div>
    </main>
</template>

<style>
.choodmap {
    display: grid;
    grid-template-columns: 1fr 40px repeat(2, 1fr 1fr 40px) 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}
</style>

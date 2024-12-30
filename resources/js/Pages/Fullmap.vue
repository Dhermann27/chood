<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';
import Map from "@/Components/chood/Map.vue";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    services: Array,
    dogs: Object,
    outhouseDogs: Array,
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
    <Head title="Fullmap"/>
    <div class="bg-gray-90 text-black/50 ">
        <div class="w-full max-w-full">
            <main>
                <div class="w-1920 h-1080 choodmap items-center justify-center">
                    <Map :cabins="cabins" :dogs="dogs" :outhouse-dogs="outhouseDogs" :services="services"
                         :photoUri="photoUri" :maxlength="8" :card-width="91" :card-height="106"/>
                </div>
            </main>
        </div>
    </div>
</template>

<style>
.choodmap {
    display: grid;
    width: 1820px;
    height: 980px;
    text-align: center;
    grid-template-columns: 91px repeat(8, 20px 91px 91px) 20px 1fr;
    grid-template-rows: repeat(4, 106px) 20px repeat(5, 106px);
}
</style>

<script setup>
import {onBeforeUnmount, onMounted, ref} from "vue";
import {formatTime} from "@/utils.js";

const hours = ref([]);
const breaks = ref([]);
const localChecksum = ref('');
let refreshInterval;

async function updateData() {

    try {
        const response = await axios.get(`/api/mealmap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data?.checksum) {
            hours.value = response.data.hours;
            breaks.value = response.data.breaks;
            localChecksum.value = response.data.checksum;
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

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
    <div class="min-h-screen flex">
        <!-- Left column -->
        <div class="w-1/2 flex-grow flex items-center justify-center divider">
            <p class="text-2xl">Camper Feeding Instructions</p>
        </div>

        <!-- Right column -->
        <div class="w-1/2 flex-grow flex flex-col justify-center items-center p-5">
            <div class="text-3xl mb-10">Daily Rotation</div>

            <table class="w-full bg-amber-100">
                <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th>Small Yard</th>
                    <th>Large Yard</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(assignments, hour) in hours" :id="hour">
                    <td class="border border-black px-4 py-2">{{ formatTime(hour) }}</td>
                    <td class="border border-black px-4 py-2">{{ assignments[0]?.first_name }}</td>
                    <td class="border border-black px-4 py-2">{{ assignments[1]?.first_name }}</td>
                </tr>
                </tbody>
            </table>


            <table class="w-full bg-blue-200 m-10">
                <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th>First Break</th>
                    <th>Lunch</th>
                    <th>Second Break</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="employee in breaks">
                    <td class="border border-black px-4 py-2">{{ employee.first_name }}</td>
                    <td class="border border-black px-4 py-2">{{ formatTime(employee.next_first_break) }}</td>
                    <td class="border border-black px-4 py-2">{{ formatTime(employee.next_lunch_break) }}</td>
                    <td class="border border-black px-4 py-2">{{ formatTime(employee.next_second_break) }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.divider {
    border-right: 25px solid #9e1b32;
}
</style>

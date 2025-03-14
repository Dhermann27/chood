<script setup>
import {computed, onBeforeUnmount, onMounted, ref} from "vue";
import {formatTime} from "@/utils.js";
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    photoUri: String,
});

const breaks = ref([]);
const dogs = ref([]);
const hours = ref([]);
const localChecksum = ref('');
let refreshInterval;
const cardHeight = computed(() => 900 / Math.max(dogs.value.length, 3));

async function updateData() {

    try {
        const response = await axios.get(`/api/mealmap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data?.checksum) {
            breaks.value = response.data.breaks;
            dogs.value = response.data.dogs;
            hours.value = response.data.hours;
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
    <div class="min-h-screen flex flex-col items-center justify-center">

        <div class="w-full flex justify-center">
            <div class="w-1/2 flex-grow flex flex-col items-center ps-3 divider">
                <div class="text-3xl mb-10">Dog Feeding Instructions</div>

                <div class="grid grid-cols-1 gap-4">
                    <div v-for="dog in dogs" :key="dog.pet_id" class="flex pb-2 border-b-2">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: '150px'}">
                            <DogCard :dogs="[dog]" :photoUri="props.photoUri" :maxlength="20" :card-height="cardHeight"/>
                        </div>

                        <div class="flex-grow flex flex-col items-start justify-start p-4">
                            <div v-for="feeding in dog.feedings" :key="feeding.id" class="mb-5">
                                {{ feeding.type }}: {{ feeding.description }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>


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
    </div>
</template>

<style scoped>
.divider {
    border-right: 25px solid #9e1b32;
}
</style>

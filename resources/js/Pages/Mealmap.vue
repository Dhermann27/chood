<script setup>
import {computed, onBeforeUnmount, onMounted, ref} from "vue";
import {formatTime} from "@/utils.js";
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    dogsPerPage: Number,
    photoUri: String,
});

const breaks = ref([]);
const dogs = ref([]);
const fohStaff = ref('');
const hours = ref([]);
const localChecksum = ref('');
let refreshInterval;
const currentDogIndex = ref(0);
const currentLoadingIndex = ref(0);
const cardHeight = computed(() => 800 / Math.min(Math.max(dogs.value.length, 3), props.dogsPerPage));

const handleImageLoaded = () => {
    while (++currentLoadingIndex.value < dogs.value?.length) {
        if (dogs.value[currentLoadingIndex.value].photoUri) {
            break;
        }
    }
};

async function updateData() {
    try {
        const response = await axios.get(`/api/mealmap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data.checksum) {
            breaks.value = response.data.breaks;
            dogs.value = response.data.dogs;
            fohStaff.value = response.data.fohStaff;
            hours.value = response.data.hours;
            localChecksum.value = response.data.checksum;
        } else if (dogs.value.length > props.dogsPerPage) {
            currentDogIndex.value += props.dogsPerPage;
            if (currentDogIndex.value >= dogs.value.length) currentDogIndex.value = 0;
        }

    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

const currentDogs = computed(() => {
    if (!dogs.value.length) return [];
    const startIndex = currentDogIndex.value;
    const endIndex = startIndex + props.dogsPerPage;
    return dogs.value.slice(startIndex, endIndex);
});

const progressBarStyle = computed(() => ({
    left: (currentDogIndex.value / dogs.value.length) * 100 + '%',
    width: (Math.min(props.dogsPerPage, dogs.value.length - currentDogIndex.value) / dogs.value.length) * 100 + '%',
    color: 'white',
}));


onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, 15000);
});

onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>


<template>
    <div class="h-full w-full flex flex-col items-center justify-center">
        <div class="w-full grid grid-cols-2 gap-4 h-full">
            <div class="flex flex-col ps-3 items-center divider pt-10">
                <div class="text-3xl mb-2">Dog Feeding Instructions</div>

                <div v-if="dogs && dogs?.length > props.dogsPerPage" class="flex justify-center gap-2 mb-4 w-full">
                    <div class="h-6 bg-gray-200 rounded-full w-3/4">
                        <div class="relative h-6 bg-blue-600 rounded-full text-center" :style="progressBarStyle">
                            {{ currentDogIndex + 1 }} - {{
                                Math.min(currentDogIndex + props.dogsPerPage, dogs?.length)
                            }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 w-full ">
                    <div v-for="(dog, index) in currentDogs" :key="index" class="flex pb-2 border-b-2">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: '150px'}">
                            <DogCard :dogs="[dog]" :photoUri="props.photoUri" :maxlength="20" :card-height="cardHeight"
                                     :shouldLoad="index === currentLoadingIndex" @imageLoaded="handleImageLoaded"/>
                        </div>

                        <div class="flex-grow flex flex-col items-start justify-center p-4 text-2xl">
                            <div v-for="feeding in dog.feedings" :key="feeding.id" class="flex-col justify-center">
                                <font-awesome-icon :icon="['fas', 'bowl-food']" class="me-2"/>
                                {{ feeding.type.trim() }}
                                <span v-if="feeding.type && feeding.description">: </span>
                                {{ feeding.description.trim() }}
                            </div>
                            <div v-for="medication in dog.medications" :key="medication.id"
                                 class="flex-col justify-center">
                                <font-awesome-icon v-if="medication.type_id === 18"
                                                   :icon="['fas', 'prescription-bottle-pill']" class="me-2"/>
                                <font-awesome-icon v-if="medication.type_id === 15" :icon="['fas', 'note-medical']"
                                                   class="me-2"/>
                                {{ medication.type.trim() }}
                                <span v-if="medication.type && medication.description">:&nbsp;</span>
                                {{ medication.description.trim() }}
                            </div>
                            <div v-for="allergy in dog.allergies" :key="allergy.id"
                                 class="flex-col justify-center text-red-700">
                                <font-awesome-icon :icon="['fas', 'hand-dots']" class="me-2"/>
                                ALLERGY: {{ allergy.description.trim() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="flex flex-col items-center pt-10">
                <div class="text-3xl mb-2">Daily Rotation</div>
                <div v-if="fohStaff" class="text-base mb-2">{{ fohStaff }}</div>

                <table class="w-3/4 bg-amber-100">
                    <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th>Small Yard</th>
                        <th>Large Yard</th>
                        <th>Floaters</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(assignments, hour) in hours" :id="hour">
                        <td class="border border-black px-4 py-2">{{ formatTime(hour) }}</td>
                        <td class="border border-black px-4 py-2">{{ assignments[0]?.first_name }}</td>
                        <td class="border border-black px-4 py-2">{{ assignments[1]?.first_name }}</td>
                        <td class="border border-black px-4 py-2">{{ assignments[2] }}</td>
                    </tr>
                    </tbody>
                </table>


                <table class="w-3/4 bg-blue-200 m-10">
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
    border-right: 10px solid #9e1b32;
}
</style>

<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onMounted, onUnmounted, ref} from 'vue'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Map from "@/Components/chood/Map.vue";
import Multiselect from "vue-multiselect";
import {ControlSchemes} from "@/controlSchemes.js";
import DogCard from "@/Components/chood/DogCard.vue";
import {getYardGridStyle} from "@/utils.js";

const props = defineProps({
    cabins: Array,
    photoUri: String,
});

const breakButtons = [
    {label: '15', value: '15'},
    {label: '30', value: '30'},
    {label: '45', value: '45'},
    {label: '60', value: '60'},
    {label: '120', value: '120'},
    {label: 'Lunch', value: '1000'},
    {label: 'Until Marked', value: '999'},
]

const dogs = ref(null);
const employees = ref(null);
const statuses = ref(null);
const statusMessage = ref(null);
const statusClass = ref('text-greyhound');
const homebaseId = ref(null);
const todo = ref(null);
const targets = ref({
    'dogsToAssign': [],
    'cabin_id': 0,
    'cabin_short_name': '',
    'break_duration': 0,
    'lunch_notes': '1 Bag'
});
const step = ref(1);
const localChecksum = ref('');
const frequency = 10000;
const is1pmOrLater = ref(false);
const restColumns = computed(() => Math.ceil(Math.sqrt((16 / 9) * (dogsOnBreak.value.length + 1))));
const restRows = computed(() => Math.ceil((dogsOnBreak.value.length + 1) / restColumns.value));
const restGridStyle = computed(() => getYardGridStyle(restRows.value, restColumns.value));
const restCardWidth = computed(() => (770 - (restColumns.value - 1) * 10) / restColumns.value);
const restCardHeight = computed(() => (290 - (restRows.value - 1) * 10) / restRows.value);
let counter = 0;
let refreshInterval;

const dogsOnBreak = computed(() => {
    return dogs.value.filter(dog => dog.rest_starts_at !== null);
});

const dogsNotOnBreak = computed(() => {
    return dogs.value.filter(dog => dog.rest_starts_at === null);
});

const dogsByCabin = computed(() => {
    const grouped = {};

    dogs.value.forEach(dog => {
        const key = dog.cabin_id ?? 'unassigned';
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(dog);
    });
    return grouped;
});

async function updateData() {
    const response = await axios.get(`/task/data/` + localChecksum.value);

    if (response.data && localChecksum.value !== response.data?.checksum) {
        dogs.value = response.data.dogs;
        employees.value = response.data.employees;
        statuses.value = response.data.statuses;
        localChecksum.value = response.data.checksum;
    }

    if (step.value !== 1 && counter++ > 2) {
        statusMessage.value = null;
        step.value = 1;
        counter = 0;
    }
    clearInterval(refreshInterval);
    refreshInterval = setInterval(updateData, frequency);
}

const prevStep = () => {
    statusMessage.value = null;
    counter = 0;
    if (step.value > 1) step.value--
}

const nextStep = () => {
    statusMessage.value = null;
    counter = 0;
    if (step.value < 4) step.value++
}

const handleEmployeeClick = (employee) => {
    homebaseId.value = employee.homebase_user_id;
    nextStep();
}

const handleTaskClick = (thisTodo) => {
    is1pmOrLater.value = new Date().getHours() >= 13;
    todo.value = thisTodo;
    nextStep();
}

const handleTargetClick = (cabin) => {
    if (todo.value === 'assignCabin') {
        targets.value = {
            ...targets.value, // Preserve existing properties
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name
        };
        if (targets.value['dogsToAssign'].length > 0) nextStep();
    } else if (todo.value === 'cleanCabin' && statuses.value.hasOwnProperty(cabin.id)) {
        targets.value = {
            homebase_user_id: homebaseId.value,
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name,
        };
        nextStep();
    }
};

const handleAssignDogUpdate = () => {
    if (targets.value['dogsToAssign'].length > 0 && targets.value['cabin_id'] > 0) nextStep();
};

const handleBreakDogUpdate = (breakDuration) => {
    targets.value.break_duration = breakDuration;
    if (targets.value['dogsToAssign'].length > 0) nextStep();
};

const handleBreakDogDelete = (dog) => {
    targets.value['dogsToAssign'] = dog;
    todo.value = `markReturned/${dog.id}`;
    nextStep();
};

const handleFinishAction = async (action) => {
    if (action === 'Done' || action === 'More') {
        axios({
            method: 'POST',
            url: `/task/${todo.value}`,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: targets.value,
        }).then((response) => {
            localChecksum.value = '';
            clearInterval(refreshInterval);
            updateData();
            refreshInterval = setInterval(updateData, frequency);

            statusMessage.value = response.data?.message;
            statusClass.value = 'text-meadow';
        }).catch((error) => {
            if (error.response && error.response.status === 419) {
                if (confirm('Your session has expired due to inactivity. Would you like to reload the page?')) {
                    window.location.reload();
                }
            } else {
                statusMessage.value = `Error: ${error.response?.data?.message || 'Unable to complete action'}`;
                statusClass.value = 'text-alerted';
            }
        });

        statusMessage.value = `Processing ${action} action...`;
        statusClass.value = 'text-greyhound';

    }
    targets.value = {'dogsToAssign': [], 'cabin_id': 0, 'cabin_short_name': '', 'break_duration': 0, 'lunch_notes': '1 Bag'};
    counter = 0;
    if (todo.value.includes('markReturned')) todo.value = 'startBreak';
    step.value = action === 'Done' ? 1 : 3;
}

const breakStatus = computed(() => {
    const d = targets.value.break_duration;
    if (d === '1000') return 'on lunch break';
    if (d === '999') return 'out until Marked';
    return `rest for ${d} minutes`;
});

onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, frequency);
});

onUnmounted(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Task Entry"/>
    <div class="flex flex-col items-center h-screen p-4">
        <template v-if="step === 1">
            <h1 class="text-3xl font-header mb-4">Hi! Huaryoo?</h1>
            <div class="grid grid-cols-4 gap-4 w-full h-full overflow-y-auto">
                <button
                    v-for="employee in employees"
                    :key="employee.id"
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex flex-col items-center justify-center w-full"
                    @click="handleEmployeeClick(employee)">

                    <img
                        :src="`/images/staff/${employee.homebase_user_id}.png`"
                        :alt="employee.first_name"
                        class="w-[80%] h-[80%] rounded-full object-cover mb-4"
                    />
                    <span>{{ employee.first_name }}</span>
                </button>
            </div>
        </template>

        <template v-else-if="step === 2">
            <h1 class="text-3xl font-header mb-4">So, watchadoin?</h1>
            <div class="grid grid-cols-3 gap-4 w-[75vw] h-[75vh]">
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('assignCabin')">
                    <font-awesome-icon :icon="['fas', 'house-circle-check']" class="me-5"/>
                    Assigning a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('cleanCabin')">
                    <font-awesome-icon :icon="['fas', 'broom']" class="me-5"/>
                    Cleaned a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('setLunch')">
                    <font-awesome-icon :icon="['fas', 'turkey']" class="me-5"/>
                    Set Lunch
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('startBreak')">
                    <font-awesome-icon :icon="['fas', 'alarm-clock']" class="me-5"/>
                    Rest Break
                </button>
            </div>
            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 3">
            <h1 class="text-3xl font-header mb-4">Cool! Which one?</h1>

            <template v-if="todo === 'assignCabin'">
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" multiple
                    :options="dogsByCabin['unassigned']"
                    label="firstname"
                    placeholder="Select Dog(s) (Required)"
                    @update:modelValue="handleAssignDogUpdate">
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <img v-if="option.photoUri" :src="props.photoUri + option.photoUri"
                                 :alt="'Picture of ' + option.firstname" class="dog-photo"
                                 @error="e => e.target.style.display = 'none'"/>
                            <span class="text-3xl ml-10">{{ option.firstname }}</span>
                        </div>
                    </template>
                </multiselect>
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="dogsByCabin"
                         :controls="ControlSchemes.SELECT_CABIN" maxlength="6"
                         :card-width="46" :card-height="55" :photoUri="photoUri" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'cleanCabin'">
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="[]" :controls="ControlSchemes.SELECT_CABIN"
                         :card-width="46" :card-height="57" maxlength="6" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'setLunch'">
                <h3 class="text-xl font-subheader uppercase mb-4">Set a dog's lunch</h3>
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" multiple :options="dogs"
                    label="firstname" placeholder="Select Dog(s) (Required)">
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <img v-if="option.photoUri" :src="props.photoUri + option.photoUri"
                                 :alt="'Picture of ' + option.firstname" class="dog-photo"
                                 @error="e => e.target.style.display = 'none'"/>
                            <span class="text-3xl ml-10">{{ option.firstname }}</span>
                        </div>
                    </template>
                </multiselect>
                <label for="lunch-notes" class="block text-lg mb-2">Lunch notes</label>
                <form @submit.prevent="nextStep" class="flex items-stretch w-full max-w-3xl">
                    <input id="lunch-notes" v-model="targets.lunch_notes" type="text"
                        placeholder="Example: 1 cup kibble + 1/2 pouch wet" inputmode="text"
                        autocapitalize="sentences" autocomplete="off"
                        class="flex-1 h-16 px-5 text-2xl border-2 border-gray-300 rounded-l-2xl rounded-r-none border-r-0 focus:outline-none" />
                    <button type="submit"
                        class="h-16 px-10 text-2xl bg-crimson text-white border-2 border-gray-300 border-l-0 rounded-r-2xl">
                        Set
                    </button>
                </form>
            </template>
            <template v-else-if="todo === 'startBreak'">
                <h3 class="text-xl font-subheader uppercase mb-4">Start a Break</h3>
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" multiple :options="dogsNotOnBreak"
                    label="firstname" placeholder="Select Dog(s) (Required)">
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <img v-if="option.photoUri" :src="props.photoUri + option.photoUri"
                                 :alt="'Picture of ' + option.firstname" class="dog-photo"
                                 @error="e => e.target.style.display = 'none'"/>
                            <span class="text-3xl ml-10">{{ option.firstname }}</span>
                        </div>
                    </template>
                </multiselect>
                <div class="flex gap-2 text-white text-xl">
                    <button v-for="btn in breakButtons" :key="btn.value"
                            :disabled="btn.value === '1000' && is1pmOrLater"
                            class="bg-caregiver py-4 px-6 rounded-2xl hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="handleBreakDogUpdate(btn.value)">
                        {{ btn.label }}
                    </button>
                </div>
                <h3 class="text-xl font-subheader uppercase my-4">Mark dog as returned to yard</h3>
                <div class="items-center justify-center p-1" :style="restGridStyle">
                    <div v-for="(dog, index) in dogsOnBreak" :id="index"
                         :style="{height: restCardHeight + 'px', width: restCardWidth + 'px'}">
                        <DogCard :dogs="[dog]" :photoUri="props.photoUri" @click="handleBreakDogDelete(dog)"
                                 :card-width="restCardWidth" :card-height="restCardHeight"/>
                    </div>
                </div>
            </template>
            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 4">
            <div class="fixed inset-0 bg-greyhound flex justify-center items-center">
                <div class="bg-white p-6 rounded-lg w-2/3">
                    <h3 class="text-2xl mb-4 text-center">
                        <template v-if="todo === 'assignCabin'">
                            {{ targets.dogsToAssign.map(dog => dog.firstname).join(', ') }}
                            in Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                        <template v-else-if="todo === 'cleanCabin'">
                            Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                        <template v-else-if="todo === 'setLunch'">
                            {{ targets.dogsToAssign.map(dog => dog.firstname).join(', ') }} should get a lunch, right?
                        </template>
                        <template v-else-if="todo === 'startBreak'">
                            {{ targets.dogsToAssign.map(dog => dog.firstname).join(', ') }} {{ breakStatus }}, right?
                        </template>
                        <template v-else-if="todo.includes('markReturned')">
                            {{ targets.dogsToAssign.firstname }} is back in yard, right?
                        </template>
                    </h3>
                    <div class="flex justify-between mb-4 text-3xl">
                        <button @click="handleFinishAction('Done')"
                            class="px-16 py-10 bg-meadow text-white rounded-md flex items-center space-x-2">
                            <font-awesome-icon :icon="['fas', 'badge-check']"/>
                            <span>Done</span>
                        </button>
                        <button @click="handleFinishAction('Undo')"
                            class="px-16 py-10 bg-gray-500 text-white rounded-md flex items-center space-x-2">
                            <font-awesome-icon :icon="['fas', 'rotate-left']"/>
                            <span>Undo</span>
                        </button>
                        <button @click="handleFinishAction('More')"
                            class="px-16 py-10 bg-caregiver text-white rounded-md flex items-center space-x-2">
                            <font-awesome-icon :icon="['fas', 'cowbell-circle-plus']"/>
                            <span>More</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="statusMessage" class="text-3xl mt-4 text-center" :class="statusClass">
            {{ statusMessage }}
        </div>
    </div>
    <i class="cabin cabin-empty"></i>
</template>

<style>
.choodmap {
    display: grid;
    text-align: center;
    grid-template-columns: 1fr repeat(8, 20px 1fr 1fr) 20px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}

.cabin {
    border-width: 5px;
}

.cabin-empty {
    font-size: 22px;
}
</style>
<style scoped>
.dog-photo {
    width: 250px;
    height: 100px;
    max-width: 250px; /* Prevent image from exceeding 200px width */
    max-height: 100px; /* Prevent image from exceeding 50px height */
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 5px;
    flex-shrink: 0; /* Prevent the image from shrinking */
}

.dog-option-item {
    display: flex;
    align-items: center; /* Vertically align text with image */
}
</style>

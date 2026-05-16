<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onMounted, onUnmounted, ref} from 'vue'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Map from "@/Components/chood/Map.vue";
import Multiselect from "vue-multiselect";
import {ControlSchemes} from "@/controlSchemes.js";
import DogCard from "@/Components/chood/DogCard.vue";
import {getYardGridStyle} from "@/utils.js";
import MoveDogs from "@/Pages/Task/MoveDogs.vue";

const props = defineProps({
    cabins: Array,
    photoUri: String,
    breakTypes: Array,
});

const FREQUENCY = 10000;

const dogs = ref(null);
const employees = ref(null);
const openYards = ref(null);
const statuses = ref(null);
const statusMessage = ref(null);
const statusClass = ref('text-greyhound');
const wiwId = ref(null);
const todo = ref(null);
const targets = ref({
    'dogsToAssign': [],
    'yardsToAssign': [],
    'cabin_id': 0,
    'cabin_short_name': '',
    'break_type_id': null,
    'lunch_notes': '1 Bag'
});
const step = ref(1);
const localChecksum = ref('');
const showNoCabinWarning = ref(false);
const is1pmOrLater = ref(false);
const restColumns = computed(() => Math.ceil(Math.sqrt((16 / 9) * (dogsOnBreak.value.length + 1))));
const restRows = computed(() => Math.ceil((dogsOnBreak.value.length + 1) / restColumns.value));
const restGridStyle = computed(() => getYardGridStyle(restRows.value, restColumns.value, false));
const restCardWidth = computed(() => (770 - (restColumns.value - 1) * 10) / restColumns.value);
const restCardHeight = computed(() => (290 - (restRows.value - 1) * 10) / restRows.value);
const dogsOnBreak = computed(() => {
    return dogs.value.filter(dog => dog.rest_starts_at !== null && !dog.checked_out_at);
});
const dogsNotOnBreak = computed(() => {
    return dogs.value.filter(dog => dog.rest_starts_at === null && dog.pet_id !== null && !dog.checked_out_at);
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
const moveDogEnabled = computed(() => openYards.value.length >= 3);
const feedingCabinEnabled = computed(() => dogsWithCabinMates.value.length > 0);
const dogsWithCabinMates = computed(() => {
    if (!dogs.value) return [];
    const counts = {};
    dogs.value.forEach(dog => {
        if (dog.cabin_id && dog.is_boarding && dog.pet_id !== null && !dog.checked_out_at) counts[dog.cabin_id] = (counts[dog.cabin_id] || 0) + 1;
    });
    return dogs.value.filter(dog => dog.cabin_id && dog.is_boarding && dog.pet_id !== null && !dog.checked_out_at && counts[dog.cabin_id] > 1);
});
const markReturnedIsWalked = computed(() => {
    const dog = targets.value.dogsToAssign;
    if (dog?.break_type?.behavior !== 'walks_only' || !dog?.rest_starts_at) return false;
    const elapsed = (Date.now() - new Date(dog.rest_starts_at).getTime()) / 60000;
    return elapsed >= dog.break_type.duration_minutes;
});
const breakStatus = computed(() => {
    const bt = props.breakTypes?.find(t => t.id === targets.value.break_type_id);
    if (!bt) return 'on break';
    if (bt.behavior === 'lunch') return 'on lunch break';
    if (bt.behavior === 'unlimited') return `in ${bt.label}`;
    if (bt.behavior === 'walks_only') return 'marked as walks only';
    return `resting for ${bt.duration_minutes} minutes`;
});

let counter = 0;
let refreshInterval;

function preloadDogPhotos(dogs) {
    if (!dogs) return;
    dogs.forEach(dog => {
        if (!dog?.photoUri) return;
        const img = new Image();
        img.src = dog.photoUri;
    });
}

async function updateData() {
    const response = await axios.get(`/task/data/` + localChecksum.value);

    if (response.data && localChecksum.value !== response.data?.checksum) {
        dogs.value = response.data.dogs;
        openYards.value = response.data.openYards;
        employees.value = response.data.employees;
        statuses.value = response.data.statuses;
        localChecksum.value = response.data.checksum;

        preloadDogPhotos(dogs.value);
    }

    if (step.value !== 1 && counter++ > 2) {
        statusMessage.value = null;
        step.value = 1;
        counter = 0;
    }
    clearInterval(refreshInterval);
    refreshInterval = setInterval(updateData, FREQUENCY);
}

function prevStep() {
    statusMessage.value = null;
    counter = 0;
    if (step.value > 1) step.value--;
}

function nextStep() {
    statusMessage.value = null;
    counter = 0;
    if (step.value < 4) step.value++;
}

function handleEmployeeClick(employee) {
    wiwId.value = employee.wiw_user_id;
    nextStep();
}

function handleTaskClick(thisTodo) {
    is1pmOrLater.value = new Date().getHours() >= 13;
    todo.value = thisTodo;
    nextStep();
}

function handleTargetClick(cabin) {
    if (todo.value === 'assignCabin') {
        targets.value = {
            ...targets.value, // Preserve existing properties
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name
        };
        if (targets.value['dogsToAssign'].length > 0) nextStep();
    } else if (todo.value === 'assignFeedingCabin') {
        const dummy = (dogsByCabin.value[cabin.id] ?? []).find(d => d.pet_id === null);
        if (dummy) {
            todo.value = 'clearFeedingCabin';
            targets.value = {
                ...targets.value,
                cabin_id: cabin.id,
                cabin_short_name: cabin.short_name,
                dummy_display_name: dummy.display_name
            };
            nextStep();
            return;
        }
        targets.value = {
            ...targets.value,
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name,
        };
        if (targets.value.dogsToAssign?.id) nextStep();
    } else if (todo.value === 'cleanCabin') {
        targets.value = {
            wiw_user_id: wiwId.value,
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name,
            is_cleaned: statuses.value.hasOwnProperty(cabin.id)
        };
        nextStep();
    }
}

function handleFeedingDogUpdate() {
    counter = 0;
    if (targets.value.dogsToAssign?.id && targets.value.cabin_id > 0) nextStep();
}

function handleAssignDogUpdate() {
    counter = 0;
    if (targets.value['dogsToAssign'].length > 0 && targets.value['cabin_id'] > 0) nextStep();
}

function addAllBoarders() {
    const existingIds = new Set(targets.value.dogsToAssign.map(d => d.id));
    const boarders = dogsNotOnBreak.value.filter(d => d.is_boarding && d.pet_id !== null && !existingIds.has(d.id));
    targets.value.dogsToAssign = [...targets.value.dogsToAssign, ...boarders];
}

function handleBreakDogSelect(dog) {
    counter = 0;
    if (!dog.cabin_id) showNoCabinWarning.value = true;
}

function handleBreakDogUpdate(breakTypeId) {
    counter = 0;
    targets.value.break_type_id = breakTypeId;
    if (targets.value['dogsToAssign'].length > 0) nextStep();
}

function handleNoCabinAssign() {
    showNoCabinWarning.value = false;
    targets.value = {
        ...targets.value,
        dogsToAssign: [],
        cabin_id: 0,
        cabin_short_name: '',
        break_type_id: null,
    };
    todo.value = 'assignCabin';
}

function handleNoCabinDismiss() {
    showNoCabinWarning.value = false;
    targets.value.dogsToAssign = targets.value.dogsToAssign.filter(d => d.cabin_id);
}

function handleBreakDogDelete(dog) {
    targets.value['dogsToAssign'] = dog;
    todo.value = `markReturned/${dog.id}`;
    nextStep();
}

function handleYardChange(pendingMoves) {
    const payload = Object.entries(pendingMoves).map(([dog_id, yard_id]) => ({
        dog_id: Number(dog_id),
        yard_id: Number(yard_id),
    }));
    if (!payload.length) return;
    targets.value['yardsToAssign'] = payload;
    nextStep();
}

async function handleFinishAction(action) {
    if (action === 'Done' || action === 'More') {
        const isClearFeeding = todo.value === 'clearFeedingCabin';
        axios({
            method: isClearFeeding ? 'DELETE' : 'POST',
            url: isClearFeeding ? '/task/assignFeedingCabin' : `/task/${todo.value}`,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: targets.value,
        }).then((response) => {
            localChecksum.value = '';
            clearInterval(refreshInterval);
            updateData();
            refreshInterval = setInterval(updateData, FREQUENCY);

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
    targets.value = {
        'dogsToAssign': [],
        'yardsToAssign': [],
        'cabin_id': 0,
        'cabin_short_name': '',
        'break_type_id': null,
        'lunch_notes': '1 Bag'
    };
    counter = 0;
    if (todo.value.includes('markReturned')) todo.value = 'startBreak';
    if (todo.value === 'clearFeedingCabin') todo.value = 'assignFeedingCabin';
    step.value = action === 'Done' ? 1 : 3;
}

onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, FREQUENCY);
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
                        :src="`/images/staff/${employee.wiw_user_id}.png`"
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
                    <FontAwesomeIcon :icon="['fas', 'house-circle-check']" class="me-5"/>
                    Assigning a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('cleanCabin')">
                    <FontAwesomeIcon :icon="['fas', 'broom']" class="me-5"/>
                    Cleaned a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('setLunch')">
                    <FontAwesomeIcon :icon="['fas', 'turkey']" class="me-5"/>
                    Set Lunch
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('startBreak')">
                    <FontAwesomeIcon :icon="['fas', 'alarm-clock']" class="me-5"/>
                    Rest Break
                </button>
                <button class="text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition
                bg-caregiver text-white hover:bg-blue-500 disabled:bg-gray-400 disabled:text-gray-200
                disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:bg-gray-400"
                        :disabled="!feedingCabinEnabled" @click="handleTaskClick('assignFeedingCabin')">
                    <FontAwesomeIcon :icon="['fas', 'utensils']" class="me-5"/>
                    <span v-if="feedingCabinEnabled">Assign Feeding Cabin</span>
                    <span v-else>No cabin siblings</span>
                </button>
                <button class="text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition
                bg-caregiver text-white hover:bg-blue-500 disabled:bg-gray-400 disabled:text-gray-200
                disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:bg-gray-400"
                        :disabled="!moveDogEnabled" @click="handleTaskClick('moveDog')">
                    <FontAwesomeIcon :icon="['fas', 'arrows-up-down-left-right']" class="me-5"/>
                    <span v-if="moveDogEnabled">Move Dogs between Yards</span>
                    <span v-else>Only 2 yards open</span>
                </button>
            </div>
            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-2" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 3">
            <h1 class="text-3xl font-header mb-4">Cool! Which one?</h1>

            <template v-if="todo === 'assignCabin'">
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" multiple track-by="id"
                    :options="(dogsByCabin['unassigned'] ?? []).filter(d => !d.is_boarding && !d.checked_out_at)"
                    label="display_name"
                    placeholder="Select Dog(s) (Required)" @update:modelValue="handleAssignDogUpdate">
                    <template #tag="{ option, remove }">
                        <span class="multiselect__tag" @mousedown.prevent="remove(option)">{{
                                option.display_name
                            }}</span>
                    </template>
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <div v-if="option.photoUri" class="dog-photo-wrap">
                                <img :src="option.photoUri" :alt="option.display_name"
                                     @error="e => e.target.parentElement.style.display = 'none'"/>
                            </div>
                            <span class="text-3xl ml-10">{{ option.display_name }}</span>
                        </div>
                    </template>
                </multiselect>
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="dogsByCabin"
                         :controls="ControlSchemes.SELECT_CABIN" :maxlength="6"
                         :card-width="49" :card-height="58" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'cleanCabin'">
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="[]" :controls="ControlSchemes.SELECT_CABIN"
                         :card-width="49" :card-height="60" :maxlength="6" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'assignFeedingCabin'">
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" track-by="id" :options="dogsWithCabinMates" label="display_name"
                    placeholder="Select Dog (Required)" @update:modelValue="handleFeedingDogUpdate">
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <div v-if="option.photoUri" class="dog-photo-wrap">
                                <img :src="option.photoUri" :alt="option.display_name"
                                     @error="e => e.target.parentElement.style.display = 'none'"/>
                            </div>
                            <span class="text-3xl ml-10">{{ option.display_name }}</span>
                        </div>
                    </template>
                </multiselect>
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="dogsByCabin"
                         :controls="ControlSchemes.SELECT_CABIN" :maxlength="6"
                         :card-width="49" :card-height="58" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'setLunch'">
                <h3 class="text-xl font-subheader uppercase mb-4">Set a dog's lunch</h3>
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 bg-crimson placeholder:text-crimson"
                    v-model="targets.dogsToAssign" multiple track-by="id"
                    :options="dogs.filter(d => d.pet_id !== null && !d.checked_out_at)" label="display_name"
                    placeholder="Select Dog(s) (Required)">
                    <template #tag="{ option, remove }">
                        <span class="multiselect__tag" @mousedown.prevent="remove(option)">{{
                                option.display_name
                            }}</span>
                    </template>
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <div v-if="option.photoUri" class="dog-photo-wrap">
                                <img :src="option.photoUri" :alt="option.display_name"
                                     @error="e => e.target.parentElement.style.display = 'none'"/>
                            </div>
                            <span class="text-3xl ml-10">{{ option.display_name }}</span>
                        </div>
                    </template>
                </multiselect>
                <label for="lunch-notes" class="block text-lg mb-2">Lunch notes</label>
                <form @submit.prevent="nextStep" class="flex items-stretch w-full max-w-3xl">
                    <input id="lunch-notes" v-model="targets.lunch_notes" type="text"
                           placeholder="Example: 1 cup kibble + 1/2 pouch wet" inputmode="text"
                           autocapitalize="sentences" autocomplete="off"
                           class="flex-1 h-16 px-5 text-2xl border-2 border-gray-300 rounded-l-2xl rounded-r-none border-r-0 focus:outline-none"/>
                    <button type="submit"
                            class="h-16 px-10 text-2xl bg-crimson text-white border-2 border-gray-300 border-l-0 rounded-r-2xl">
                        Set
                    </button>
                </form>
            </template>
            <template v-else-if="todo === 'startBreak'">
                <h3 class="text-xl font-subheader uppercase mb-4">Start a Break</h3>
                <div class="flex items-start gap-2 w-2/3 mb-5">
                    <multiselect
                        class="dogsToAssign-multiselect border-2 bg-crimson placeholder:text-crimson"
                        v-model="targets.dogsToAssign" multiple track-by="id" :options="dogsNotOnBreak"
                        label="display_name"
                        placeholder="Select Dog(s) (Required)" @click="counter = 0;" @select="handleBreakDogSelect">
                        <template #tag="{ option, remove }">
                            <span class="multiselect__tag" @mousedown.prevent="remove(option)">{{
                                    option.display_name
                                }}</span>
                        </template>
                        <template #option="{ option }">
                            <div class="dog-option-item">
                                <div v-if="option.photoUri" class="dog-photo-wrap">
                                    <img :src="option.photoUri" :alt="option.display_name"
                                         @error="e => e.target.parentElement.style.display = 'none'"/>
                                </div>
                                <span class="text-3xl ml-10">{{ option.display_name }}</span>
                            </div>
                        </template>
                    </multiselect>
                    <button @click="addAllBoarders"
                            class="bg-caregiver text-white px-4 py-3 rounded-xl shrink-0 text-xl">
                        <FontAwesomeIcon :icon="['fas', 'bed']"/>
                    </button>
                </div>
                <div class="flex gap-2 text-white text-xl">
                    <button v-for="bt in breakTypes" :key="bt.id"
                            :disabled="bt.behavior === 'lunch' && is1pmOrLater"
                            class="bg-caregiver py-4 px-6 rounded-2xl hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="handleBreakDogUpdate(bt.id)">
                        {{ bt.label }}
                    </button>
                </div>
                <h3 class="text-xl font-subheader uppercase my-4">Mark dog as returned to yard</h3>
                <div class="items-center justify-center p-1" :style="restGridStyle">
                    <div v-for="(dog, index) in dogsOnBreak" :id="index"
                         :style="{height: restCardHeight + 'px', width: restCardWidth + 'px'}">
                        <DogCard :dogs="[dog]" @click="handleBreakDogDelete(dog)"
                                 :card-width="restCardWidth" :card-height="restCardHeight"/>
                    </div>
                </div>
            </template>
            <template v-else-if="todo === 'moveDog'">
                <MoveDogs
                    :dogs="dogs.filter(d => (d.is_daycare || d.is_boarding || d.is_interview) && !d.checked_out_at && d.pet_id !== null)"
                    :yards="openYards" @changed="counter = 0;" @submit="handleYardChange" style="height: 650px;"/>
            </template>

            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-4" @click="prevStep">Back</button>
        </template>
        <template v-else-if="step === 4">
            <div class="fixed inset-0 bg-greyhound flex justify-center items-center">
                <div class="bg-white p-6 rounded-lg w-2/3">
                    <h3 class="text-2xl mb-4 text-center">
                        <template v-if="todo === 'assignCabin'">
                            {{ targets.dogsToAssign.map(dog => dog.display_name).join(', ') }}
                            in Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                        <template v-else-if="todo === 'assignFeedingCabin'">
                            {{ targets.dogsToAssign.display_name }} eats in Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                        <template v-else-if="todo === 'cleanCabin'">
                            Cabin {{ targets.cabin_short_name }} is {{ targets.is_cleaned ? 'clean' : 'dirty' }}, right?
                        </template>
                        <template v-else-if="todo === 'setLunch'">
                            {{ targets.dogsToAssign.map(dog => dog.display_name).join(', ') }} should get a lunch,
                            right?
                        </template>
                        <template v-else-if="todo === 'startBreak'">
                            {{ targets.dogsToAssign.map(dog => dog.display_name).join(', ') }} {{ breakStatus }}, right?
                        </template>
                        <template v-else-if="todo.includes('markReturned')">
                            {{ targets.dogsToAssign.display_name }}
                            {{ markReturnedIsWalked ? 'has been walked' : 'is back in yard' }}, right?
                        </template>
                        <template v-else-if="todo === 'clearFeedingCabin'">
                            Clear {{ targets.dummy_display_name }}'s feeding cabin, right?
                        </template>
                        <template v-else-if="todo.includes('moveDog')">
                            Assign dogs to yards, right?
                        </template>
                    </h3>
                    <div class="flex justify-between mb-4 text-3xl">
                        <button @click="handleFinishAction('Done')"
                                class="px-6 py-10 bg-meadow text-white rounded-md flex items-center space-x-2">
                            <FontAwesomeIcon :icon="['fas', 'badge-check']"/>
                            <span>Done</span>
                        </button>
                        <button @click="handleFinishAction('Undo')"
                                class="px-6 py-10 bg-gray-500 text-white rounded-md flex items-center space-x-2">
                            <FontAwesomeIcon :icon="['fas', 'rotate-left']"/>
                            <span>Undo</span>
                        </button>
                        <button @click="handleFinishAction('More')"
                                class="px-6 py-10 bg-caregiver text-white rounded-md flex items-center space-x-2">
                            <FontAwesomeIcon :icon="['fas', 'cowbell-circle-plus']"/>
                            <span>More</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="showNoCabinWarning" class="fixed inset-0 bg-greyhound flex justify-center items-center z-50">
            <div class="bg-white p-6 rounded-lg w-2/3 text-center">
                <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']" class="text-5xl text-alerted mb-4"/>
                <h3 class="text-2xl mb-2">
                    {{ targets.dogsToAssign.filter(d => !d.cabin_id).map(d => d.display_name).join(', ') }}
                    {{ targets.dogsToAssign.filter(d => !d.cabin_id).length === 1 ? "doesn't" : "don't" }} have a cabin assigned!
                </h3>
                <p class="text-xl text-gray-600 mb-6">Assign a cabin first, then start the rest break.</p>
                <div class="flex justify-center gap-6 text-2xl">
                    <button @click="handleNoCabinAssign"
                            class="px-8 py-4 bg-caregiver text-white rounded-xl flex items-center gap-3">
                        <FontAwesomeIcon :icon="['fas', 'house-circle-check']"/>
                        Assign Cabin
                    </button>
                    <button @click="handleNoCabinDismiss"
                            class="px-8 py-4 bg-gray-500 text-white rounded-xl flex items-center gap-3">
                        <FontAwesomeIcon :icon="['fas', 'xmark']"/>
                        Cancel
                    </button>
                </div>
            </div>
        </div>

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
    grid-template-columns: 1fr repeat(8, 10px 1fr 1fr) 10px 1fr;
    grid-template-rows: repeat(4, 1fr) 10px repeat(5, 1fr);
}

.cabin {
    border-width: 5px;
}

.cabin-empty {
    font-size: 22px;
}
</style>
<style scoped>
.dog-photo-wrap {
    width: 75px;
    height: 75px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
}

.dog-photo-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dog-option-item {
    display: flex;
    align-items: center;
}

:deep(.multiselect__tag) {
    padding: 12px 20px;
    font-size: 1.25rem;
    cursor: pointer;
    user-select: none;
}

</style>

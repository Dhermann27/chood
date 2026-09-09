<script setup>
import {Head} from '@inertiajs/vue3';
import {onMounted, onUnmounted} from 'vue';
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Map from "@/Components/chood/Map.vue";
import Multiselect from "vue-multiselect";
import {ControlSchemes} from "@/controlSchemes.js";
import DogCard from "@/Components/chood/DogCard.vue";
import MoveDogs from "@/Pages/Task/MoveDogs.vue";
import {useMapPolling} from "@/Composables/useMapPolling.js";
import {useTaskFlow} from "@/Composables/useTaskFlow.js";

const props = defineProps({
    cabins: Array,
    breakTypes: Array,
});

// Forward ref: restart is assigned after useMapPolling returns, but onSuccess captures it by closure
const restartRef = {
    fn: () => {
    }
};
let lastInteractionAt = Date.now();

const {
    dogs, employees, openYards, statuses, statusMessage, statusClass,
    wiwId, todo, restMinutes, staffImageCache, targets, step,
    showNoCabinWarning, is1pmOrLater,
    empColumns, empRows, restColumns, restRows, restGridStyle, restCardWidth, restCardHeight,
    dogsOnBreak, dogsNotOnBreak, dogsByCabin, moveDogEnabled, feedingCabinEnabled,
    dogsWithCabinMates, markReturnedIsWalked, breakStatus,
    preloadStaffPhoto, preloadDogPhotos,
    prevStep, nextStep,
    handleEmployeeClick, handleTaskClick, handleTargetClick,
    handleFeedingDogUpdate, handleAssignDogUpdate, addAllBoarders,
    handleBreakDogSelect, handleBreakDogUpdate, handleTimerStart, handleRotateStart,
    handleNoCabinAssign, handleNoCabinDismiss, handleBreakDogDelete,
    handleYardChange, handleFinishAction,
} = useTaskFlow(props.breakTypes, {
    onInteraction: () => {
        lastInteractionAt = Date.now();
    },
    onSuccess: () => restartRef.fn(),
});

const {restart} = useMapPolling('/task/data/', 10000, (data) => {
    dogs.value = data.dogs;
    openYards.value = data.openYards;
    employees.value = data.employees;
    statuses.value = data.statuses;
    employees.value?.forEach(preloadStaffPhoto);
    preloadDogPhotos(dogs.value);
});

restartRef.fn = restart;

// Every 10s: reset to step 1 after 30s idle; reload after 60 min page age + 2 min idle on step 1
let idleInterval;
let mountedAt;
onMounted(() => {
    mountedAt = Date.now();
    idleInterval = setInterval(() => {
        const idle = Date.now() - lastInteractionAt;
        if (step.value !== 1 && idle > 30000) {
            statusMessage.value = null;
            step.value = 1;
        }
        if (step.value === 1 && idle > 120000 && Date.now() - mountedAt >= 3600000) {
            window.location.reload();
        }
    }, 10000);
});
onUnmounted(() => clearInterval(idleInterval));
</script>

<template>
    <Head title="Task Entry"/>
    <div class="flex flex-col items-center h-screen p-4">
        <template v-if="step === 1">
            <h1 class="text-3xl font-header mb-4">Hi! Huaryoo?</h1>
            <div
                :style="{display: 'grid', gridTemplateColumns: `repeat(${empColumns}, minmax(0, 1fr))`,
                gridTemplateRows: `repeat(${empRows}, minmax(0, 1fr))`,gap: '1rem'}"
                class="w-full flex-1 min-h-0">
                <div v-for="employee in employees" :key="employee.id" role="button"
                     class="flex items-center justify-center w-full h-full cursor-pointer"
                     @click="handleEmployeeClick(employee)">
                    <div v-if="staffImageCache.has(employee.wiw_user_id)"
                         class="relative w-full h-full rounded-2xl overflow-hidden ring-[3px] ring-caregiver">
                        <img
                            :src="`/images/staff/${employee.wiw_user_id}.png`" :alt="employee.first_name"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <div v-else
                         class="w-full h-full bg-caregiver rounded-2xl flex items-center justify-center text-white text-5xl font-semibold">
                        {{ employee.first_name }}
                    </div>
                </div>
            </div>
        </template>

        <template v-else-if="step === 2">
            <h1 class="text-3xl font-header mb-4">So, watchadoin?</h1>
            <div class="grid grid-cols-3 gap-4 w-[75vw] h-[75vh]">
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition hover:bg-blue-500"
                    @click="handleTaskClick('assignCabin')">
                    <FontAwesomeIcon :icon="['fas', 'house-circle-check']" class="me-5"/>
                    Assigning a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition hover:bg-blue-500"
                    @click="handleTaskClick('cleanCabin')">
                    <FontAwesomeIcon :icon="['fas', 'broom']" class="me-5"/>
                    Cleaned a Cabin
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition hover:bg-blue-500"
                    @click="handleTaskClick('setLunch')">
                    <FontAwesomeIcon :icon="['fas', 'turkey']" class="me-5"/>
                    Set Lunch
                </button>
                <button
                    class="bg-caregiver text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center transition hover:bg-blue-500"
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
                        placeholder="Select Dog(s) (Required)" @select="handleBreakDogSelect">
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
                <div class="flex gap-2 text-white text-xl flex-wrap">
                    <button
                        v-for="bt in breakTypes.filter(bt => bt.behavior === 'countdown' && bt.duration_minutes !== null)"
                        :key="bt.id" class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-blue-500"
                        @click="handleBreakDogUpdate(bt.id)">
                        {{ bt.label }}
                    </button>
                    <input v-model="restMinutes" type="number" min="1" max="480" placeholder="Minutes"
                           @keydown.enter="handleTimerStart" @blur="handleTimerStart"
                           class="bg-caregiver py-2 px-4 rounded-2xl text-white placeholder-blue-200 w-36 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"/>
                    <button @click="handleRotateStart"
                            class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-blue-500">
                        Rotate
                    </button>
                    <button v-for="bt in breakTypes.filter(bt => bt.behavior !== 'countdown')" :key="bt.id"
                            :disabled="bt.behavior === 'lunch' && is1pmOrLater"
                            class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
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
                    :yards="openYards" @changed="lastInteractionAt = Date.now()" @submit="handleYardChange"
                    style="height: calc(100vh - 220px);"/>
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
                    {{ targets.dogsToAssign.filter(d => !d.cabin_id).length === 1 ? "doesn't" : "don't" }} have a cabin
                    assigned!
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

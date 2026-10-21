<script setup>
import {inject} from 'vue';
import Multiselect from 'vue-multiselect';
import DogCard from '@/Components/chood/DogCard.vue';
import DogMultiselectOption from '@/Components/chood/DogMultiselectOption.vue';

const {
    targets, restMinutes, dogsNotOnBreak, breakTypes, is1pmOrLater, restGridStyle, restCardHeight, restCardWidth,
    dogsOnBreak, selectedReturnDogs, handleBreakDogSelect, addAllBoarders, handleBreakDogUpdate, handleTimerStart,
    handleRotateStart, toggleReturnDog, selectAllReturnDogs, handleReturnSubmit,
} = inject('taskFlow');
</script>

<template>
    <h3 class="text-xl font-subheader uppercase mb-4">Start a Break</h3>
    <div class="flex items-start gap-2 w-2/3 mb-5">
        <multiselect
            class="dogsToAssign-multiselect border-2 bg-crimson placeholder:text-crimson"
            v-model="targets.dogsToAssign" multiple track-by="id" :options="dogsNotOnBreak"
            label="display_name"
            placeholder="Select Dog(s) (Required)" @select="handleBreakDogSelect">
            <template #tag="{ option, remove }">
                <span class="multiselect__tag" @mousedown.prevent="remove(option)">{{ option.display_name }}</span>
            </template>
            <template #option="{ option }">
                <DogMultiselectOption :option="option"/>
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
            :key="bt.id" class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-caregiver/80"
            @click="handleBreakDogUpdate(bt.id)">
            {{ bt.label }}
        </button>
        <input v-model="restMinutes" type="number" min="1" max="480" placeholder="Minutes"
               @keydown.enter="handleTimerStart" @blur="handleTimerStart"
               class="bg-caregiver py-2 px-4 rounded-2xl text-white placeholder-white/60 w-36 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"/>
        <button @click="handleRotateStart"
                class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-caregiver/80">
            Rotate
        </button>
        <button v-for="bt in breakTypes.filter(bt => bt.behavior !== 'countdown')" :key="bt.id"
                :disabled="bt.behavior === 'lunch' && is1pmOrLater"
                class="bg-caregiver py-2 px-4 rounded-2xl hover:bg-caregiver/80 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="handleBreakDogUpdate(bt.id)">
            {{ bt.label }}
        </button>
    </div>
    <div class="items-center justify-center p-1 mt-[5px]" :style="restGridStyle">
        <div v-for="(dog, index) in dogsOnBreak" :id="index"
             :style="{height: restCardHeight + 'px', width: restCardWidth + 'px'}"
             :class="['relative cursor-pointer rounded transition-all',
                 selectedReturnDogs.some(d => d.id === dog.id) ? 'ring-4 ring-meadow' : 'opacity-70 hover:opacity-100']"
             @click="toggleReturnDog(dog)">
            <DogCard :dogs="[dog]" :card-height="restCardHeight"/>
        </div>
    </div>
    <div class="flex gap-3 justify-end mt-3">
        <button @click="selectAllReturnDogs" class="px-4 py-2 text-lg rounded-2xl bg-greyhound text-white">
            {{
                selectedReturnDogs.length === dogsOnBreak.length && dogsOnBreak.length > 0 ? 'Deselect All' : 'Select All'
            }}
        </button>
        <button @click="handleReturnSubmit" :disabled="selectedReturnDogs.length === 0"
                class="px-4 py-2 text-lg rounded-2xl bg-crimson text-white disabled:opacity-40">
            Return {{ selectedReturnDogs.length }} to Yard
        </button>
    </div>
</template>

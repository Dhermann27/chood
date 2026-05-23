<script setup>
import {computed, ref, watch} from 'vue';
import Draggable from 'vuedraggable';
import {getYardGridStyle} from '@/utils.js';
import MoveDogCard from "./MoveDogCard.vue";

const ASPECT_RATIO = 4 / 3;
const GAP = 10;

const props = defineProps({
    dogs: {type: Array, required: true},
    yards: {type: Array, required: true},
});

const emit = defineEmits(['changed', 'submit']);
const yardTiles = ref({});       // { [yardId]: tiles[] }
const pendingMoves = ref({});    // { [dogId]: yardId }
const LARGE_YARD_IDS = [1001, 1002];
const SMALL_YARD_IDS = [1000, 1003];

const openYards = computed(() => props.yards ?? []);
const moveDogYards = computed(() => {
    const hasMultipleLarge = openYards.value.filter(y => LARGE_YARD_IDS.includes(y.id)).length >= 2;
    const hasMultipleSmall = openYards.value.filter(y => SMALL_YARD_IDS.includes(y.id)).length >= 2;
    return openYards.value.filter(y =>
        (LARGE_YARD_IDS.includes(y.id) && hasMultipleLarge) ||
        (SMALL_YARD_IDS.includes(y.id) && hasMultipleSmall)
    );
});
const maxTiles = computed(() =>
    Math.max(1, ...moveDogYards.value.map(y => yardTiles.value[y.id]?.length ?? 0))
);
const columns = computed(() => Math.ceil(Math.sqrt(ASPECT_RATIO * maxTiles.value)));
const rows = computed(() => Math.ceil(maxTiles.value / columns.value));
const gridStyle = computed(() => getYardGridStyle(rows.value, columns.value));
const innerHeight = computed(() => moveDogYards.value.length === 4 ? 200 : 410);
const cardHeight = computed(() => (innerHeight.value - (rows.value - 1) * GAP) / rows.value);
const pendingCount = computed(() => Object.keys(pendingMoves.value).length);

function rebuildYardTiles() {
    const next = {};

    moveDogYards.value.forEach(y => {
        next[y.id] = [];
    });
    props.dogs.forEach(d => {
        let yardId = pendingMoves.value[d.id] ?? d.yard_id;
        if (!yardId || !next[yardId]) {
            const isLarge = d.size_letter?.includes('L');
            const preferredIds = isLarge ? LARGE_YARD_IDS : SMALL_YARD_IDS;
            const fallback = [...moveDogYards.value].reverse().find(y => preferredIds.includes(y.id));
            if (!fallback) return;
            yardId = fallback.id;
        }
        if (!next[yardId]) return;
        next[yardId].push(d);
    });
    moveDogYards.value.forEach(y => {
        next[y.id].sort((a, b) =>
            (a.display_name ?? '').localeCompare(b.display_name ?? '')
        );
    });

    yardTiles.value = next;
}

function dragGroupForYard(yard) {
    if (LARGE_YARD_IDS.includes(yard.id)) return {name: 'lane-large', pull: true, put: true};
    if (SMALL_YARD_IDS.includes(yard.id)) return {name: 'lane-small', pull: true, put: true};
    return {name: 'lane-other', pull: true, put: true};
}

function validateMove(e) {
    const size = e.draggedContext.element.size_letter ?? '';
    if (size === 'LS') return true;
    const targetId = parseInt(e.to.dataset.yardId);
    if (size.includes('L')) return LARGE_YARD_IDS.includes(targetId);
    return SMALL_YARD_IDS.includes(targetId);
}

function handleDragPreview(e, yard) {
    if (!e?.added?.element) return;
    const dog = e.added.element;

    pendingMoves.value = {
        ...pendingMoves.value,
        [dog.id]: yard.id
    };

    rebuildYardTiles();

    emit('changed', pendingMoves.value);
}

function moveAll() {
    const basicLarge = moveDogYards.value.find(y => y.id === LARGE_YARD_IDS[0]);
    const basicSmall = moveDogYards.value.find(y => y.id === SMALL_YARD_IDS[0]);
    const newMoves = {...pendingMoves.value};
    props.dogs.forEach(d => {
        const currentYard = pendingMoves.value[d.id] ?? d.yard_id;
        const isLarge = d.size_letter?.includes('L') && d.size_letter !== 'LS';
        const target = isLarge ? basicLarge : basicSmall;
        if (!target || currentYard === target.id) return;
        newMoves[d.id] = target.id;
    });
    pendingMoves.value = newMoves;
    rebuildYardTiles();
    emit('changed', pendingMoves.value);
}

function undoMoves() {
    pendingMoves.value = {};
    rebuildYardTiles();
}

function submitMoves() {
    if (!Object.keys(pendingMoves.value).length) return;
    emit('submit', pendingMoves.value);
}

watch(() => [moveDogYards.value, props.dogs], rebuildYardTiles, {deep: true, immediate: true});
</script>

<template>
    <div class="w-full h-full flex flex-col">
        <div class="flex-1 min-h-0 overflow-hidden">
            <div class="grid gap-4 w-full"
                 :class="moveDogYards.length === 4 ? 'grid-cols-2 grid-rows-2' : 'grid-cols-2'">
                <div v-for="yard in moveDogYards" :key="yard.id"
                     class="bg-white rounded-2xl p-3 border-2 flex flex-col overflow-hidden"
                     :class="moveDogYards.length === 4 ? 'h-[260px]' : 'h-[460px]'">
                    <div class="mb-2 shrink-0">
                        <div class="text-2xl font-header">{{ yard.name }}</div>
                    </div>

                    <Draggable :list="yardTiles[yard.id]" item-key="id" :group="dragGroupForYard(yard)" :sort="true"
                               :move="validateMove" :data-yard-id="yard.id" class="rounded-xl bg-gray-50 h-full"
                               :style="gridStyle" @change="e => handleDragPreview(e, yard)">>
                        <template #item="{ element }">
                            <div class="flex items-center justify-center w-full"
                                 :style="{ height: cardHeight + 'px' }">
                                <div class="cursor-grab active:cursor-grabbing w-full h-full">
                                    <MoveDogCard :dog="element" :card-height="cardHeight"/>
                                </div>
                            </div>
                        </template>
                    </Draggable>
                </div>
            </div>
        </div>

        <div class="shrink-0 pt-4">
            <div class="mx-auto w-full max-w-[900px] flex items-center justify-center gap-10">
                <div class="text-2xl">
                    Pending moves: <span class="font-bold">{{ pendingCount }}</span>
                </div>

                <div class="flex gap-3">
                    <button
                        class="px-10 py-4 text-2xl rounded-2xl bg-greyhound text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="pendingCount === 0" @click="undoMoves">
                        Clear All
                    </button>

                    <button
                        class="px-10 py-4 text-2xl rounded-2xl bg-caregiver text-white"
                        @click="moveAll">
                        Move All
                    </button>

                    <button
                        class="px-10 py-4 text-2xl rounded-2xl bg-crimson text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="pendingCount === 0" @click="submitMoves">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>



<script setup>
import {computed, ref, watch} from 'vue';
import Draggable from 'vuedraggable';
import {getYardGridStyle} from '@/utils.js';
import MoveDogCard from "./MoveDogCard.vue";

const props = defineProps({
    dogs: {type: Array, required: true},
    yards: {type: Array, required: true},
    photoUri: {type: String, required: true},
    imageCache: {type: Set, required: true},
});

const emit = defineEmits(['changed', 'submit']);

const aspectRatio = 4 / 3;
const innerWidth = 541;
const gap = 10;
const yardTiles = ref({});       // { [yardId]: tiles[] }
const pendingMoves = ref({});    // { [dogId]: yardId }

const openYards = computed(() => props.yards ?? []);
const moveDogYards = computed(() => {
    return openYards.value.length === 3 ? openYards.value.slice(1, 3) : openYards.value.slice(0, 4);
});
const maxTiles = computed(() =>
    Math.max(1, ...moveDogYards.value.map(y => yardTiles.value[y.id]?.length ?? 0))
);
const columns = computed(() => Math.ceil(Math.sqrt(aspectRatio * maxTiles.value)));
const rows = computed(() => Math.ceil(maxTiles.value / columns.value));
const gridStyle = computed(() => getYardGridStyle(rows.value, columns.value));
const innerHeight = computed(() => moveDogYards.value.length === 4 ? 200 : 410);
const cardWidth = computed(() => (innerWidth - (columns.value - 1) * gap) / columns.value);
const cardHeight = computed(() => (innerHeight.value - (rows.value - 1) * gap) / rows.value);
const pendingCount = computed(() => Object.keys(pendingMoves.value).length);

function rebuildYardTiles() {
    const next = {};

    moveDogYards.value.forEach(y => {
        next[y.id] = [];
    });
    props.dogs.forEach(d => {
        const yardId = pendingMoves.value[d.id] ?? d.yard_id;
        if (!next[yardId]) return;
        next[yardId].push(d);
    });
    moveDogYards.value.forEach(y => {
        next[y.id].sort((a, b) =>
            (a.firstname ?? '').localeCompare(b.firstname ?? '')
        );
    });

    yardTiles.value = next;
}

function dragGroupForYard(yard) {
    const type = (yard.type ?? '').toLowerCase();
    if (type === 'large' || type === 'active') return {name: 'lane-large', pull: true, put: true};
    if (type === 'small' || type === 'medium') return {name: 'lane-small', pull: true, put: true};
    return {name: 'lane-other', pull: true, put: true};
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
                               class="rounded-xl bg-gray-50 h-full" :style="gridStyle"
                               @change="e => handleDragPreview(e, yard)">>
                        <template #item="{ element }">
                            <div class="flex items-center justify-center"
                                 :style="{ width: cardWidth + 'px', height: cardHeight + 'px' }">
                                <div class="cursor-grab active:cursor-grabbing w-full h-full">
                                    <MoveDogCard :dog="element" :photoUri="props.photoUri"
                                                 :card-width="cardWidth" :card-height="cardHeight"/>
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
                        class="px-10 py-4 text-2xl rounded-2xl bg-crimson text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="pendingCount === 0" @click="submitMoves">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>



<script setup>
import {Head, router} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import DogCard from '@/Components/chood/DogCard.vue';
import {VueDatePicker} from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    breaks: Array,
    from: String,
    to: String,
});

const today = new Date().toISOString().slice(0, 10);
const selectedDate = ref([props.from ?? today, props.to ?? today]);
const copied = ref(false);
const pendingDeleteId = ref(null);


const byDog = computed(() => {
    const groups = {};
    props.breaks.forEach(rb => {
        if (!groups[rb.dog_name]) groups[rb.dog_name] = [];
        groups[rb.dog_name].push(rb);
    });
    return Object.entries(groups);
});

function formatTime(dt) {
    if (!dt) return null;
    const d = new Date(dt);
    const h = d.getHours();
    const m = d.getMinutes().toString().padStart(2, '0');
    return `${h % 12 || 12}:${m}${h < 12 ? 'am' : 'pm'}`;
}

function actualMinutes(rb) {
    const end = rb.ended_at ? new Date(rb.ended_at) : new Date();
    return Math.round((end - new Date(rb.started_at)) / 60000);
}

function intendedMinutes(rb) {
    return rb.rest_minutes ?? rb.break_type?.duration_minutes ?? null;
}

function isOverrun(rb) {
    const intended = intendedMinutes(rb);
    return intended !== null && actualMinutes(rb) > intended * 1.5;
}

function goToToday() {
    selectedDate.value = [today, today];
    router.visit('/reports/restbreaks');
}

function formatDateLabel(iso) {
    return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', {weekday: 'long', month: 'long', day: 'numeric'});
}

function onDateClose() {
    const [from, to] = selectedDate.value ?? [];
    if (!from) return;
    if (from === today && (!to || to === today)) {
        router.visit('/reports/restbreaks');
        return;
    }
    const path = (to && to !== from) ? `/reports/restbreaks/${from}/${to}` : `/reports/restbreaks/${from}`;
    router.visit(path);
}

function confirmDelete(id) {
    pendingDeleteId.value = id;
}

function deleteBreak() {
    router.delete(`/reports/restbreaks/${pendingDeleteId.value}`, {preserveScroll: true});
    pendingDeleteId.value = null;
}

function formatShortDate(dt) {
    const d = new Date(dt);
    return d.toLocaleDateString('en-US', {weekday: 'short', month: 'numeric', day: 'numeric'});
}

async function copyToClipboard() {
    const lines = [];
    byDog.value.forEach(([dogName, dogBreaks]) => {
        const lastName = dogBreaks[0]?.dog?.lastname;
        lines.push(lastName ? `${dogName} ${lastName}` : dogName);
        dogBreaks.forEach(rb => {
            const type = rb.break_type?.label ?? '—';
            const start = formatTime(rb.started_at) ?? '—';
            const end = rb.ended_at ? formatTime(rb.ended_at) : 'Active';
            const actual = actualMinutes(rb) + 'm';
            const reasons = rb.reasons?.map(r => r.label).join(', ');
            const parts = [type, `${start} → ${end}`, actual];
            if (reasons) parts.push(reasons);
            lines.push('  ' + parts.join(' | '));
        });
        lines.push('');
    });
    try {
        await navigator.clipboard.writeText(lines.join('\n'));
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        console.error('Copy failed:', err);
    }
}
</script>

<template>
    <Head title="Rest Breaks"/>
    <div class="min-h-screen bg-greyhound/10 p-6">
        <div class="max-w-5xl mx-auto space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-header">Rest Breaks</h1>
                <div class="flex items-center gap-3">
                    <VueDatePicker v-model="selectedDate" :enable-time-picker="false" auto-apply
                                   :range="{partialRange: false}" model-type="yyyy-MM-dd"
                                   :formats="{input: 'MM/dd/yyyy'}" :hide-input-icon="true"
                                   @closed="onDateClose" :clearable="false"
                                   input-class-name="px-4 py-2 rounded-xl border border-greyhound/30 text-greyhound text-lg cursor-pointer"/>
                    <button @click="goToToday"
                            class="px-4 py-2 bg-greyhound/20 text-greyhound rounded-xl text-lg">
                        Today
                    </button>
                    <button @click="copyToClipboard"
                            class="flex items-center gap-2 px-4 py-2 bg-caregiver text-white rounded-xl text-lg transition-colors"
                            :class="copied ? 'bg-meadow' : 'bg-caregiver'">
                        <FontAwesomeIcon :icon="['fas', copied ? 'check' : 'clipboard']"/>
                        {{ copied ? 'Copied!' : 'Copy to Clipboard' }}
                    </button>
                </div>
            </div>

            <div v-if="breaks.length === 0" class="bg-white rounded-2xl p-8 text-center text-greyhound text-xl">
                No rest breaks recorded for this period.
            </div>

            <div v-else class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-sm border-collapse">
                    <thead>
                    <tr class="bg-caregiver/20 text-left text-greyhound font-semibold text-base">
                        <th class="px-3 py-3 w-14"></th>
                        <th class="px-3 py-3">Dog</th>
                        <th v-if="from !== to" class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Type</th>
                        <th class="px-3 py-3">Started</th>
                        <th class="px-3 py-3">Ended</th>
                        <th class="px-3 py-3">Actual Length</th>
                        <th class="px-3 py-3">Reasons</th>
                        <th class="px-3 py-3 w-10"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="[dogName, dogBreaks] in byDog" :key="dogName">
                        <tr v-for="(rb, idx) in dogBreaks" :key="rb.id"
                            class="border-t border-greyhound/10 align-middle">
                            <td v-if="idx === 0" :rowspan="dogBreaks.length"
                                class="p-1 border-r border-greyhound/10">
                                <div style="width:70px;height:70px">
                                    <DogCard v-if="rb.dog" :dogs="[rb.dog]" :card-height="70" :maxlength="6"/>
                                    <div v-else
                                         class="w-full h-full bg-greyhound/20 rounded flex items-center justify-center text-greyhound/50 text-xs">
                                        ?
                                    </div>
                                </div>
                            </td>
                            <td v-if="idx === 0" :rowspan="dogBreaks.length"
                                class="px-3 py-2 font-semibold text-base border-r border-greyhound/10">
                                {{ dogName }}
                            </td>
                            <td v-if="from !== to" class="px-3 py-2 font-mono">{{ formatShortDate(rb.started_at) }}</td>
                            <td class="px-3 py-2">{{ rb.break_type?.label ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono">{{ formatTime(rb.started_at) }}</td>
                            <td class="px-3 py-2 font-mono"
                                :class="!rb.ended_at ? 'text-alerted font-semibold' : ''">
                                {{ rb.ended_at ? formatTime(rb.ended_at) : 'Active' }}
                            </td>
                            <td class="px-3 py-2 font-mono"
                                :class="isOverrun(rb) ? 'bg-alerted text-white' : ''">
                                {{ actualMinutes(rb) }}m
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                        <span v-for="reason in rb.reasons" :key="reason.id"
                                              class="flex items-center gap-1 px-2 py-0.5 bg-caregiver/15 text-greyhound rounded-lg">
                                            <FontAwesomeIcon :icon="['fas', reason.icon]"
                                                             class="text-caregiver text-xs"/>
                                            {{ reason.label }}
                                        </span>
                                </div>
                            </td>
                            <td class="px-2 py-2 text-center">
                                <button @click="confirmDelete(rb.id)"
                                        class="text-greyhound/40 hover:text-crimson transition-colors">
                                    <FontAwesomeIcon :icon="['fas', 'trash']"/>
                                </button>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div v-if="pendingDeleteId" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 w-80 text-center shadow-xl">
            <p class="text-xl mb-6">Delete this rest break?</p>
            <div class="flex gap-3 justify-center">
                <button @click="pendingDeleteId = null"
                        class="px-6 py-3 rounded-xl bg-greyhound/20 text-greyhound text-lg">
                    Cancel
                </button>
                <button @click="deleteBreak"
                        class="px-6 py-3 rounded-xl bg-crimson text-white text-lg">
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.dp--input) {
    width: 280px;
    font-family: FuturaBook, sans-serif;
}

:deep(.dp--clear-btn) {
    display: none;
}
</style>

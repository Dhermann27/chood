<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';

const props = defineProps({
    date: String,
    iso_date: String,
    pending_pet_ids: Array,
    fsg: Array,
    enrichment: Array,
    bath: Array,
    interviews: Array,
    boarding: Array,
    daycare: Array,
});

const sections = computed(() => [
    {key: 'fsg', title: 'Full-Service Grooming', dogs: props.fsg},
    {key: 'enrichment', title: 'Enrichment', dogs: props.enrichment},
    {key: 'bath', title: 'Bath & Basic Grooming', dogs: props.bath},
    {key: 'orientation', title: 'Orientation', dogs: props.interviews},
    {key: 'boarding', title: 'Boarding Check-Ins', dogs: props.boarding},
    {key: 'daycare', title: 'Daycare Check-Ins', dogs: props.daycare},
]);

const pendingIds = ref(new Set(props.pending_pet_ids));
const extraData = ref({});
let pollInterval = null;
const MAX_POLLS = 24; // 2 minutes at 5s intervals
let pollCount = 0;

function effectiveDog(dog) {
    const extra = extraData.value[dog.pet_id];
    return extra ? {...dog, ...extra} : dog;
}

function isPending(dog) {
    return pendingIds.value.has(dog.pet_id);
}

async function poll() {
    if (pendingIds.value.size === 0 || ++pollCount > MAX_POLLS) {
        clearInterval(pollInterval);
        return;
    }
    const ids = [...pendingIds.value];
    const params = ids.map(id => `ids[]=${id}`).join('&');
    try {
        const res = await fetch(`/dailyreports/${props.iso_date}/dogs?${params}`);
        const data = await res.json();
        for (const [petId, dogData] of Object.entries(data)) {
            extraData.value[parseInt(petId)] = dogData;
            pendingIds.value.delete(parseInt(petId));
        }
    } catch (_) {
        // silently retry next interval
    }
}

onMounted(() => {
    if (pendingIds.value.size > 0) {
        pollInterval = setInterval(poll, 5000);
    }
});

onUnmounted(() => clearInterval(pollInterval));

function formatTime(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
}

function print() {
    window.print();
}

function formatAllergies(allergies) {
    if (!allergies?.length) return '—';
    return allergies.map(a => a.description).join(', ');
}

function sectionTime(entry, key) {
    if (['orientation', 'boarding', 'daycare'].includes(key)) return formatTime(entry.checkin ?? entry.scheduled_start);
    if (!entry.scheduled_start) return null;
    return formatTime(entry.scheduled_start);
}

function serviceNames(entry, key) {
    if (key === 'orientation') return '—';
    if (key === 'boarding') return [entry.food_type, entry.feeding_notes].filter(Boolean).join(' — ') || '—';
    if (key === 'daycare') return '—';
    return entry.service_name ?? '—';
}

function specialistFor(entry, key) {
    if (['orientation', 'boarding', 'daycare'].includes(key)) return '—';
    return entry.assigned_to || '—';
}
</script>

<template>
    <div class="p-6 print:p-0">
        <div class="no-print flex items-center gap-6 mb-6">
            <h1 class="text-2xl font-bold">Daily Reports — {{ date }}</h1>
            <button class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-700"
                    @click="print">Print
            </button>
        </div>

        <section v-for="section in sections" :key="section.key" class="report-section">
            <div class="section-header">
                <h2 class="text-xl font-bold">{{ section.title }}</h2>
                <span class="text-sm text-gray-500 print:text-gray-700">{{ date }}</span>
            </div>

            <p v-if="!section.dogs.length" class="text-gray-400 italic mt-4 print:text-gray-600">
                No dogs scheduled.
            </p>

            <table v-else class="w-full mt-3 border-collapse text-sm">
                <thead>
                <tr class="border-b-2 border-gray-800 text-left">
                    <th class="py-1 pr-4">Time</th>
                    <th class="py-1 pr-4">Name</th>
                    <th class="py-1 pr-4">M/F</th>
                    <th class="py-1 pr-4">Weight</th>
                    <th class="py-1 pr-4">Size</th>
                    <th class="py-1 pr-4">Breed</th>
                    <th class="py-1 pr-4">Cabin</th>
                    <th class="py-1 pr-4">Services</th>
                    <th class="py-1 pr-4">Allergies</th>
                    <th class="py-1">Specialist</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(dog, i) in section.dogs" :key="dog.id ?? dog.pet_id ?? i"
                    class="border-b border-gray-200 print:border-gray-400">
                    <td class="py-1 pr-4">
                        <template v-if="sectionTime(dog, section.key) !== null">{{
                                sectionTime(dog, section.key)
                            }}
                        </template>
                        <span v-else class="font-bold text-amber-600 print:text-amber-800">
                                <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']" class="mr-1"/>Unscheduled
                            </span>
                    </td>
                    <td class="py-1 pr-4 font-medium">{{ dog.display_name }}</td>
                    <td class="py-1 pr-4">
                        <FontAwesomeIcon v-if="isPending(dog) && effectiveDog(dog).gender === null"
                                         :icon="['fas', 'spinner-third']" :spin="true" class="text-gray-300"/>
                        <template v-else>{{ effectiveDog(dog).gender ?? '—' }}</template>
                    </td>
                    <td class="py-1 pr-4">
                        <FontAwesomeIcon v-if="isPending(dog) && effectiveDog(dog).weight === null"
                                         :icon="['fas', 'spinner-third']" :spin="true" class="text-gray-300"/>
                        <template v-else>{{
                                effectiveDog(dog).weight ? effectiveDog(dog).weight + ' lb' : '—'
                            }}
                        </template>
                    </td>
                    <td class="py-1 pr-4">
                        <FontAwesomeIcon v-if="isPending(dog) && effectiveDog(dog).size_letter === null"
                                         :icon="['fas', 'spinner-third']" :spin="true" class="text-gray-300"/>
                        <template v-else>{{ effectiveDog(dog).size_letter ?? '—' }}</template>
                    </td>
                    <td class="py-1 pr-4">{{ dog.breed ?? '—' }}</td>
                    <td class="py-1 pr-4">{{ dog.cabin?.short_name ?? '—' }}</td>
                    <td class="py-1 pr-4">{{ serviceNames(dog, section.key) }}</td>
                    <td class="py-1 pr-4 text-red-700 print:text-red-800 font-medium">
                        <FontAwesomeIcon v-if="isPending(dog) && effectiveDog(dog).allergies === null"
                                         :icon="['fas', 'spinner-third']" :spin="true" class="text-gray-300"/>
                        <template v-else>{{ formatAllergies(effectiveDog(dog).allergies) }}</template>
                    </td>
                    <td class="py-1">{{ specialistFor(dog, section.key) }}</td>
                </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>

<style scoped>
.section-header {
    display: flex;
    align-items: baseline;
    gap: 1rem;
    border-bottom: 2px solid #1f2937;
    padding-bottom: 0.25rem;
}

@media print {
    .no-print {
        display: none;
    }

    @page {
        margin: 1cm;
    }

    .report-section {
        page-break-after: always;
    }

    .report-section:last-child {
        page-break-after: avoid;
    }

    table {
        font-size: 11pt;
    }

    th {
        border-bottom: 2px solid #000;
    }

    td {
        padding: 3px 12px 3px 0;
    }
}
</style>

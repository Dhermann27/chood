<script setup>
import { computed } from 'vue';

const props = defineProps({
    date: String,
    fsg: Array,
    enrichment: Array,
    bath: Array,
    interviews: Array,
});

const sections = computed(() => [
    { key: 'fsg',         title: 'Full-Service Grooming',  dogs: props.fsg },
    { key: 'enrichment',  title: 'Enrichment',             dogs: props.enrichment },
    { key: 'bath',        title: 'Bath & Basic Grooming',  dogs: props.bath },
    { key: 'orientation', title: 'Orientation',            dogs: props.interviews },
]);

function formatTime(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function print() { window.print(); }


function formatAllergies(allergies) {
    if (!allergies?.length) return '—';
    return allergies.map(a => a.description).join(', ');
}

function appointmentTime(dog, matchFn) {
    const appt = dog.appointments?.find(matchFn);
    return appt ? formatTime(appt.scheduled_start) : '—';
}

function serviceNames(dog, excludeFn = null) {
    if (!dog.appointments?.length) return '—';
    const names = dog.appointments
        .map(a => a.service?.name)
        .filter(n => n && (!excludeFn || !excludeFn(n)));
    return names.length ? names.join(', ') : '—';
}

const isFsg  = n => n && n.toLowerCase().includes('full service');
const isBath = n => n && (n.toLowerCase().includes('bath') || n.toLowerCase().includes('basic grooming'));
</script>

<template>
    <div class="p-6 print:p-0">
        <div class="no-print flex items-center gap-6 mb-6">
            <h1 class="text-2xl font-bold">Daily Reports — {{ date }}</h1>
            <button class="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-700"
                    @click="print">Print</button>
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
                <!-- FSG -->
                <template v-if="section.key === 'fsg'">
                    <thead>
                        <tr class="border-b-2 border-gray-800 text-left">
                            <th class="py-1 pr-4">Time</th>
                            <th class="py-1 pr-4">Name</th>
                            <th class="py-1 pr-4">M/F</th>
                            <th class="py-1 pr-4">Weight</th>
                            <th class="py-1 pr-4">Size</th>
                            <th class="py-1 pr-4">Cabin</th>
                            <th class="py-1 pr-4">Services</th>
                            <th class="py-1">Allergies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dog in section.dogs" :key="dog.id"
                            class="border-b border-gray-200 print:border-gray-400">
                            <td class="py-1 pr-4">{{ appointmentTime(dog, a => isFsg(a.service?.name)) }}</td>
                            <td class="py-1 pr-4 font-medium">{{ dog.display_name }}</td>
                            <td class="py-1 pr-4">{{ dog.gender ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.weight ? dog.weight + ' lb' : '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.size_letter ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.cabin?.short_name ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ serviceNames(dog) }}</td>
                            <td class="py-1 text-red-700 print:text-red-800 font-medium">{{ formatAllergies(dog.allergies) }}</td>
                        </tr>
                    </tbody>
                </template>

                <!-- Enrichment -->
                <template v-else-if="section.key === 'enrichment'">
                    <thead>
                        <tr class="border-b-2 border-gray-800 text-left">
                            <th class="py-1 pr-4">Name</th>
                            <th class="py-1 pr-4">M/F</th>
                            <th class="py-1 pr-4">Weight</th>
                            <th class="py-1 pr-4">Size</th>
                            <th class="py-1 pr-4">Cabin</th>
                            <th class="py-1">Allergies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dog in section.dogs" :key="dog.id"
                            class="border-b border-gray-200 print:border-gray-400">
                            <td class="py-1 pr-4 font-medium">{{ dog.display_name }}</td>
                            <td class="py-1 pr-4">{{ dog.gender ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.weight ? dog.weight + ' lb' : '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.size_letter ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.cabin?.short_name ?? '—' }}</td>
                            <td class="py-1 text-red-700 print:text-red-800 font-medium">{{ formatAllergies(dog.allergies) }}</td>
                        </tr>
                    </tbody>
                </template>

                <!-- Bath & Basic Grooming -->
                <template v-else-if="section.key === 'bath'">
                    <thead>
                        <tr class="border-b-2 border-gray-800 text-left">
                            <th class="py-1 pr-4">Time</th>
                            <th class="py-1 pr-4">Name</th>
                            <th class="py-1 pr-4">M/F</th>
                            <th class="py-1 pr-4">Weight</th>
                            <th class="py-1 pr-4">Size</th>
                            <th class="py-1 pr-4">Cabin</th>
                            <th class="py-1 pr-4">Services</th>
                            <th class="py-1">Allergies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dog in section.dogs" :key="dog.id"
                            class="border-b border-gray-200 print:border-gray-400">
                            <td class="py-1 pr-4">{{ appointmentTime(dog, a => isBath(a.service?.name)) }}</td>
                            <td class="py-1 pr-4 font-medium">{{ dog.display_name }}</td>
                            <td class="py-1 pr-4">{{ dog.gender ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.weight ? dog.weight + ' lb' : '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.size_letter ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.cabin?.short_name ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ serviceNames(dog) }}</td>
                            <td class="py-1 text-red-700 print:text-red-800 font-medium">{{ formatAllergies(dog.allergies) }}</td>
                        </tr>
                    </tbody>
                </template>

                <!-- Orientation -->
                <template v-else-if="section.key === 'orientation'">
                    <thead>
                        <tr class="border-b-2 border-gray-800 text-left">
                            <th class="py-1 pr-4">Time</th>
                            <th class="py-1 pr-4">Name</th>
                            <th class="py-1 pr-4">M/F</th>
                            <th class="py-1 pr-4">Weight</th>
                            <th class="py-1 pr-4">Size</th>
                            <th class="py-1">Allergies</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dog in section.dogs" :key="dog.id"
                            class="border-b border-gray-200 print:border-gray-400">
                            <td class="py-1 pr-4">{{ formatTime(dog.checkin) }}</td>
                            <td class="py-1 pr-4 font-medium">{{ dog.display_name }}</td>
                            <td class="py-1 pr-4">{{ dog.gender ?? '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.weight ? dog.weight + ' lb' : '—' }}</td>
                            <td class="py-1 pr-4">{{ dog.size_letter ?? '—' }}</td>
                            <td class="py-1 text-red-700 print:text-red-800 font-medium">{{ formatAllergies(dog.allergies) }}</td>
                        </tr>
                    </tbody>
                </template>
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
    .no-print { display: none; }

    @page { margin: 1cm; }

    .report-section { page-break-after: always; }
    .report-section:last-child { page-break-after: avoid; }

    table { font-size: 11pt; }
    th { border-bottom: 2px solid #000; }
    td { padding: 3px 12px 3px 0; }
}
</style>

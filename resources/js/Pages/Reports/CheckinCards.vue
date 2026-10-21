<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onMounted, onUnmounted, ref} from 'vue';
import axios from 'axios';

const date = ref(new Date().toLocaleDateString('en-CA'));
const started = ref(false);
const errorMessage = ref(null);
const cards = ref([]);
const selected = ref(new Set());
const complete = ref(false);
const pendingOwners = ref(0);

function toggleAll() {
    if (selected.value.size === cards.value.length) {
        selected.value = new Set();
    } else {
        selected.value = new Set(cards.value.map(c => c.pet_id));
    }
}

function toggleCard(card) {
    const next = new Set(selected.value);
    next.has(card.pet_id) ? next.delete(card.pet_id) : next.add(card.pet_id);
    selected.value = next;
}

const selectedCards = computed(() =>
    cards.value.filter(c => selected.value.has(c.pet_id))
);

const allSelected = computed(() =>
    cards.value.length > 0 && selected.value.size === cards.value.length
);

async function handleSubmit() {
    started.value = true;
    complete.value = false;
    pendingOwners.value = 0;
    errorMessage.value = null;
    cards.value = [];
    selected.value = new Set();

    try {
        const response = await axios.post('/reports/checkin/fetch',
            {date: date.value},
            {headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}}
        );
        pollResults(response.data.id);
    } catch (error) {
        started.value = false;
        errorMessage.value = error.response?.data?.output || error.message || 'Server error';
    }
}

function pollResults(reportId) {
    const interval = setInterval(async () => {
        try {
            const response = await axios.get('/reports/checkin/results/' + reportId);
            const data = response.data.data ?? {};
            if (data.cards) {
                cards.value = data.cards;
            }
            pendingOwners.value = data.pending_owners ?? 0;
            if (data.complete) {
                complete.value = true;
                started.value = false;
                clearInterval(interval);
            }
        } catch (error) {
            errorMessage.value = error.response?.data?.message || 'Error fetching results';
            clearInterval(interval);
        }
    }, 2000);
}

function formatLine(parts) {
    return parts.filter(Boolean).join(', ');
}

function formatCardDate(dateStr) {
    if (!dateStr) return '';
    const [m, d, y] = dateStr.split('/');
    const date = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
    return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()] + ' ' + parseInt(m) + '/' + parseInt(d);
}

function nightsCount(card) {
    if (!card.checkin_date || !card.checkout_date) return null;
    const [m1, d1, y1] = card.checkin_date.split('/');
    const [m2, d2, y2] = card.checkout_date.split('/');
    const a = new Date(parseInt(y1), parseInt(m1) - 1, parseInt(d1));
    const b = new Date(parseInt(y2), parseInt(m2) - 1, parseInt(d2));
    return Math.round((b - a) / 86400000);
}

function feedingText(card) {
    return formatLine([card.food_type, ...(card.feedings ?? []), card.feeding_notes]);
}

function medsText(card) {
    return (card.medications ?? []).join('; ');
}

function adjustCardHeights() {
    document.querySelectorAll('.card-box').forEach(box => {
        const header = box.querySelector('.card-dog-name');
        const table = box.querySelector('.card-table');
        if (!header || !table) return;

        const rows = Array.from(table.querySelectorAll('tr'));
        rows.forEach(r => {
            r.style.height = '';
        });

        const available = box.clientHeight - header.offsetHeight;
        const naturalTotal = rows.reduce((sum, r) => sum + r.offsetHeight, 0);

        if (naturalTotal > 0 && available > naturalTotal) {
            const scale = available / naturalTotal;
            rows.forEach(r => {
                r.style.height = Math.floor(r.offsetHeight * scale) + 'px';
            });
        }
    });
}

function resetCardHeights() {
    document.querySelectorAll('.card-table tr').forEach(r => {
        r.style.height = '';
    });
}

onMounted(() => {
    window.addEventListener('beforeprint', adjustCardHeights);
    window.addEventListener('afterprint', resetCardHeights);
});

onUnmounted(() => {
    window.removeEventListener('beforeprint', adjustCardHeights);
    window.removeEventListener('afterprint', resetCardHeights);
});

function printCards() {
    window.print();
}
</script>

<template>
    <Head title="Check-In Cards"/>
    <div class="flex items-center justify-center min-h-screen bg-greyhound print:hidden">
        <div class="w-full max-w-4xl p-8 space-y-6">
            <div class="bg-white shadow-md rounded-lg p-8 space-y-4">
                <h2 class="text-2xl font-header text-center">Boarding Check-In Cards</h2>
                <form @submit.prevent="handleSubmit" class="flex gap-4 items-end">
                    <div class="flex-1">
                        <label for="date" class="block font-medium text-greyhound">Date</label>
                        <input v-model="date" type="date" id="date" required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver"/>
                    </div>
                    <button type="submit" :disabled="started"
                            class="px-6 py-2 text-white bg-caregiver rounded-lg shadow-sm disabled:opacity-50">
                        {{ started ? 'Loading…' : 'Generate' }}
                    </button>
                </form>
                <div v-if="errorMessage" class="p-4 bg-alerted border rounded text-sm">{{ errorMessage }}</div>
            </div>

            <div v-if="started && cards.length === 0" class="flex justify-center py-12">
                <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin class="text-6xl text-white"/>
            </div>

            <div v-if="complete && cards.length === 0"
                 class="bg-white shadow-md rounded-lg p-6 text-center text-greyhound">
                No boarding reservations found for {{ date }}.
            </div>

            <template v-if="cards.length > 0">
                <div class="bg-white shadow-md rounded-lg p-4 flex items-center justify-between gap-4">
                    <label class="flex items-center gap-2 cursor-pointer font-medium">
                        <input type="checkbox" :checked="allSelected" @change="toggleAll" class="w-4 h-4"/>
                        {{ allSelected ? 'Deselect all' : 'Select all' }}
                        ({{ selected.size }} of {{ cards.length }})
                    </label>
                    <span v-if="pendingOwners > 0" class="flex items-center gap-2 text-sm text-greyhound/70">
                        <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin/>
                        {{ pendingOwners }} more loading…
                    </span>
                    <button @click="printCards"
                            :disabled="selected.size === 0"
                            class="px-6 py-2 bg-caregiver text-white rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        Print {{ selected.size }} Card{{ selected.size !== 1 ? 's' : '' }}
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div v-for="card in cards" :key="card.pet_id"
                         @click="toggleCard(card)"
                         :class="['bg-white shadow rounded-lg p-3 cursor-pointer border-2 transition-colors text-xs',
                             selected.has(card.pet_id) ? 'border-caregiver' : 'border-transparent opacity-60']">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" :checked="selected.has(card.pet_id)" @click.stop="toggleCard(card)"
                                   class="mt-0.5 w-4 h-4 shrink-0"/>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-sm">{{ card.animal_name }}</div>
                                <div class="text-greyhound">
                                    {{ formatLine([card.breed, card.color]) }}
                                </div>
                                <div class="text-greyhound">
                                    {{ card.owner_name }} · {{ card.owner_phone }}
                                </div>
                                <div class="truncate text-greyhound/70">
                                    {{
                                        formatCardDate(card.checkin_date)
                                    }}{{
                                        card.checkout_date ? ' → ' + formatCardDate(card.checkout_date) : ''
                                    }}{{
                                        nightsCount(card) ? ', ' + nightsCount(card) + ' night' + (nightsCount(card) !== 1 ? 's' : '') : ''
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Print output — only selected cards, 2 per page -->
    <div class="hidden print:block">
        <template v-for="(card, idx) in selectedCards" :key="card.pet_id ?? idx">
            <div :class="['card-half-sheet', idx % 2 === 1 ? 'card-bottom' : '']">
                <div class="card-box">
                    <div class="card-dog-name">
                        <span>{{ card.animal_name }}</span>
                        <span class="card-nights" v-if="nightsCount(card) || card.cabin_name">
                            <template v-if="nightsCount(card)">{{
                                    nightsCount(card)
                                }} night{{ nightsCount(card) !== 1 ? 's' : '' }}</template><template
                            v-if="nightsCount(card) && card.cabin_name">, </template><template
                            v-if="card.cabin_name">{{ card.cabin_name }}</template>
                        </span>
                    </div>
                    <table class="card-table">
                        <colgroup>
                            <col style="width:1.05in"/>
                            <col style="width:1.35in"/>
                            <col style="width:0.85in"/>
                            <col style="width:1.25in"/>
                            <col style="width:1.15in"/>
                            <col style="width:1.85in"/>
                        </colgroup>
                        <tbody>
                        <tr class="info-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'calendar-days']" class="card-icon"/>
                                Check-In:
                            </td>
                            <td class="value">{{ formatCardDate(card.checkin_date) }}</td>
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'calendar-days']" class="card-icon"/>
                                Check-Out:
                            </td>
                            <td class="value">{{ formatCardDate(card.checkout_date) }}</td>
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'clock']" class="card-icon"/>
                                Checkout Time:
                            </td>
                            <td class="writable">{{ card.checkout_time }}</td>
                        </tr>
                        <tr class="info-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'user']" class="card-icon"/>
                                Owner:
                            </td>
                            <td class="value" colspan="2">{{ card.owner_name }}</td>
                            <td class="label">Breed:</td>
                            <td class="value" colspan="2">{{ card.breed }}</td>
                        </tr>
                        <tr class="info-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'phone']" class="card-icon"/>
                                Phone:
                            </td>
                            <td class="value" colspan="2">{{ card.owner_phone }}</td>
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'palette']" class="card-icon"/>
                                Color:
                            </td>
                            <td class="value" colspan="2">{{ card.color }}</td>
                        </tr>
                        <tr class="info-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'turkey']" class="card-icon"/>
                                Feeding:
                            </td>
                            <td class="writable" colspan="5">
                                <div class="feeding-cell">
                                    <span>{{ feedingText(card) }}</span>
                                    <span v-if="card.family" class="feed-separately">
                                        <FontAwesomeIcon :icon="['far', 'square']" class="checkbox-large"/> Feed Separately
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr class="info-row">
                            <td class="label indent">Additives:</td>
                            <td class="writable additives-cell" colspan="5">
                                <FontAwesomeIcon :icon="['far', 'square']" class="checkbox-large"/>
                                Any
                                &nbsp;&nbsp;
                                <FontAwesomeIcon :icon="['far', 'square']" class="checkbox-large"/>
                                None
                            </td>
                        </tr>
                        <tr class="writable-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'stethoscope']" class="card-icon"/>
                                Medications:
                            </td>
                            <td class="writable" colspan="5">{{ medsText(card) }}</td>
                        </tr>
                        <tr class="writable-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']" class="card-icon"/>
                                Allergies:
                            </td>
                            <td class="writable" colspan="5">{{ card.allergies }}</td>
                        </tr>
                        <tr class="writable-row grows">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'cowbell-circle-plus']" class="card-icon"/>
                                Enrichments:
                            </td>
                            <td class="writable" colspan="5"></td>
                        </tr>
                        <tr class="writable-row grows">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'droplet']" class="card-icon"/>
                                Grooming:
                            </td>
                            <td class="writable" colspan="5"></td>
                        </tr>
                        <tr class="writable-row grows">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'clipboard']" class="card-icon"/>
                                Belongings:
                            </td>
                            <td class="writable" colspan="5"></td>
                        </tr>
                        <tr class="writable-row grows">
                            <td class="writable" colspan="6"></td>
                        </tr>
                        <tr class="info-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'circle-exclamation']" class="card-icon"/>
                                Emergency:
                            </td>
                            <td class="value" colspan="5">
                                {{ formatLine([card.emergency_contact_name, card.emergency_contact_phone]) }}
                            </td>
                        </tr>
                        <tr class="writable-row grows sig-row">
                            <td class="label">
                                <FontAwesomeIcon :icon="['fas', 'pen-nib']" class="card-icon"/>
                                Signature:
                            </td>
                            <td class="writable" colspan="5"></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="footer-cell">
                                <div class="footer-note">
                                    <span class="footer-item"><FontAwesomeIcon :icon="['far', 'square']"
                                                                               class="checkbox-large"/> Notified of Sunday/Holiday Hours</span>
                                    <span class="footer-item"><FontAwesomeIcon :icon="['far', 'square']"
                                                                               class="checkbox-large"/> Notified of Extended Checkout Time</span>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="idx % 2 === 1 && idx < selectedCards.length - 1" class="page-break"></div>
        </template>
    </div>
</template>

<style>
@media print {
    @page {
        size: letter portrait;
        margin: 0.25in;
    }

    .card-half-sheet {
        width: 8in;
        height: 5.15in;
        box-sizing: border-box;
        padding-bottom: 0.1in;
        border-bottom: 1px dashed #58595B;
    }

    .card-bottom {
        padding-top: 0.1in;
        padding-bottom: 0;
        border-bottom: none;
    }

    .card-box {
        width: 100%;
        height: 100%;
        box-sizing: border-box;
        border: 2px solid #9E1B32;
        border-radius: 0.12in;
        padding: 0.08in 0.18in;
        overflow: hidden;
    }

    .card-dog-name {
        font-family: 'ChunkFive', serif;
        font-size: 20pt;
        color: #9E1B32;
        border-bottom: 1.5px solid #87B3D1;
        margin-bottom: 2px;
        padding-bottom: 1px;
        line-height: 1.15;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        overflow: hidden;
    }

    .card-nights {
        font-family: 'ChunkFive', serif;
        font-size: 20pt;
        color: #9E1B32;
        white-space: nowrap;
        flex-shrink: 0;
        padding-left: 8px;
    }

    .dog-icon {
        width: 16pt !important;
        height: 16pt !important;
        color: #9E1B32;
        margin-right: 4px;
        vertical-align: middle;
    }

    .page-break {
        page-break-after: always;
    }

    .card-table {
        width: 7.5in;
        table-layout: fixed;
        border-collapse: collapse;
        font-family: 'FuturaBook', sans-serif;
        font-size: 8.5pt;
    }

    .card-table tr.info-row {
        height: 0.24in;
    }

    .card-table tr.writable-row {
        height: 0.30in;
    }

    .card-table tr.sig-row {
        height: 0.60in;
    }

    .card-table td {
        padding: 1px 3px;
        vertical-align: bottom;
    }

    .card-table .label {
        font-family: 'FuturaBold', sans-serif;
        color: #87B3D1;
        white-space: nowrap;
        overflow: hidden;
        padding-right: 4px;
    }

    .card-table .label.indent {
        padding-left: 16px;
    }

    .card-table .value {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-left: 6px;
    }

    .card-table .writable {
        border-bottom: 1px solid #58595B;
        white-space: normal;
        word-break: break-word;
    }

    .feeding-cell {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 8px;
        width: 100%;
    }

    .feed-separately {
        white-space: nowrap;
        flex-shrink: 0;
        font-family: 'FuturaBold', sans-serif;
        color: #58595B;
    }

    .card-table .additives-cell {
        text-align: right;
        white-space: nowrap;
    }

    .card-icon {
        width: 8pt !important;
        height: 8pt !important;
        color: #87B3D1;
        vertical-align: middle;
        margin-right: 2px;
    }

    .checkbox-large {
        width: 14pt !important;
        height: 14pt !important;
        color: #58595B;
        vertical-align: middle;
        margin-right: 2px;
    }

    .footer-cell {
        font-family: 'FuturaBold', sans-serif;
        font-size: 11pt;
        color: #58595B;
        padding-top: 3px;
    }

    .footer-note {
        display: flex;
        align-items: center;
        justify-content: space-evenly;
        width: 100%;
    }

    .footer-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
}
</style>

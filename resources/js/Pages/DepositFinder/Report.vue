<script setup>
import {ref} from 'vue'
import axios from 'axios'

const props = defineProps({
    sbUser: String,
    sbPass: String,
});

const username = ref(props.sbUser);
const password = ref(props.sbPass);
const date = ref(new Date().toLocaleDateString('en-CA'));
const errorMessage = ref(null);
const started = ref(false);
const results = ref([]);


function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(value);
}

async function handleSubmit() {
    started.value = true;
    results.value = [];
    errorMessage.value = null;
    try {
        const response = await axios.post('/depositfinder/login',
            {username: username.value, password: password.value, date: date.value},
            {headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}}
        );

        results.value = response.data;
        pollResults(response.data.id);
    } catch (error) {
        started.value = false;
        if (error.response?.status === 419) {
            if (confirm('Your session has expired. Reload the page?')) window.location.reload();
        } else {
            errorMessage.value = error.response?.data?.output || error.message || 'Server error';
        }
    }
}

function pollResults(reportId) {
    let pollInterval;
    pollInterval = setInterval(async () => {
        try {
            const response = await axios.get('/depositfinder/results/' + reportId);
            results.value = response.data.data;
            if ('boarding_accrual' in results.value) {
                started.value = false;
                clearInterval(pollInterval);
            }
        } catch (error) {
            errorMessage.value = error.response?.data?.output || 'Error fetching results';
            clearInterval(pollInterval);
        }
    }, 3000);
}

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
}

function stripLocation(name) {
    return name.replace(/\s*@?\s*Crestwood MO(\s*\|)?/g, '').trim();
}

async function copyFullReport(e) {
    const node = document.getElementById('report-table-wrapper');
    if (!node) return;

    const clone = node.cloneNode(true);
    clone.querySelector('button')?.remove();
    clone.querySelectorAll('.fa-clipboard, [data-icon="clipboard"]').forEach(el => el.remove());

    const style = document.createElement('style');
    style.textContent = `
    table { border-collapse: collapse; width: 100%; font-family: Calibri, sans-serif; }
    th, td.font-semibold { font-weight: 600 }
    thead tr:first-child { background-color: #f0f0f0; }
    th, td { padding: 8px; border: 1px solid #ccc; }
    tbody tr:nth-child(even) { background-color: #f9f9f9; }
    tbody tr:nth-child(odd) { background-color: #ffffff; }
    td.text-right { text-align: right; }
    td.text-center { text-align: center; }
  `;
    clone.prepend(style);
    e.currentTarget.textContent = 'Copied!';
    try {
        await navigator.clipboard.write([
            new ClipboardItem({
                'text/html': new Blob([clone.outerHTML], {type: 'text/html'}),
                'text/plain': new Blob([clone.textContent ?? ''], {type: 'text/plain'}),
            }),
        ]);
    } catch (err) {
        console.error('Copy failed:', err);
    }
}
</script>

<template>
    <div class="flex items-center justify-center min-h-screen bg-greyhound">
        <div class="w-full max-w-full flex flex-col items-center">
            <div class="w-1/2 max-w-full min-w-0 p-8 space-y-8 bg-white shadow-md rounded-lg">
                <h2 class="text-2xl font-header text-center">Daily Deposit Finder</h2>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div>
                        <label for="username" class="block font-medium text-greyhound">Gingr Username</label>
                        <input v-model="username" type="text" id="username" placeholder="Enter your Gingr username"
                               required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>
                    <div>
                        <label for="password" class="block font-medium text-greyhound">Gingr Password</label>
                        <input v-model="password" type="password" id="password" placeholder="Enter your Gingr password"
                               required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>
                    <div>
                        <label for="date" class="block font-medium text-greyhound">Date</label>
                        <input v-model="date" type="date" id="date" required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>

                    <div v-if="errorMessage" class="p-4 mb-4 bg-alerted border rounded">
                        {{ errorMessage }}
                    </div>

                    <div>
                        <button type="submit" class="w-full px-4 py-2 text-white bg-caregiver rounded-lg shadow-sm">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="Object.keys(results ?? {}).length > 0 || started" id="report-table-wrapper"
                 class="w-2/3 max-w-full min-w-0 mt-8 p-8 bg-white shadow-md rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-subheader uppercase">Date: {{ results?.report_date ?? date }}</h3>
                    <button id="copy-report-button" @click="copyFullReport($event)" :disabled="started"
                            class="w-1/3 px-4 py-2 bg-caregiver text-white text-sm rounded shadow disabled:opacity-50 disabled:cursor-not-allowed">
                        Copy Report to Clipboard
                    </button>
                </div>
                <table class="min-w-full table-auto bg-white rounded-lg">
                    <thead>
                    <tr class="bg-greyhound border-b text-center text-white font-subheader uppercase">
                        <th class="px-4 py-2 text-left" rowspan="2">Category</th>
                        <th class="px-4 py-2" colspan="2">Paid</th>
                        <th class="px-4 py-2" colspan="2">Used</th>
                    </tr>
                    <tr class="bg-greyhound border-b text-center text-white font-subheader uppercase">
                        <th class="px-4 py-2">Quantity</th>
                        <th class="px-4 py-2">Amount</th>
                        <th class="px-4 py-2">Quantity</th>
                        <th class="px-4 py-2">Amount</th>
                    </tr>
                    </thead>
                    <tbody>

                    <!-- Overall -->
                    <tr class="bg-greyhound">
                        <td colspan="5" class="text-lg text-white font-subheader uppercase">Overall</td>
                    </tr>
                    <template v-if="!('overall_paid' in results)">
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                 class="text-6xl text-greyhound py-5"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr class="border-b">
                            <td class="font-semibold">Total</td>
                            <td class="text-center">{{ results.overall_paid.qty }}</td>
                            <td class="text-right font-semibold">
                                {{ formatCurrency(results.overall_paid.total) }}
                                <FontAwesomeIcon :icon="['fas', 'clipboard']"
                                                 class="ml-2 text-caregiver cursor-pointer inline-block"
                                                 @click="() => copyToClipboard(results.overall_paid.total)"/>
                            </td>
                            <template v-if="'boarding_accrual' in results">
                                <td class="text-center">{{ results.accrual_total.qty }}</td>
                                <td class="text-right font-semibold">
                                    {{ formatCurrency(results.accrual_total.total) }}
                                    <FontAwesomeIcon :icon="['fas', 'clipboard']"
                                                     class="ml-2 text-caregiver cursor-pointer inline-block"
                                                     @click="() => copyToClipboard(results.accrual_total.total)"/>
                                </td>
                            </template>
                            <template v-else>
                                <td colspan="2" class="text-center">
                                    <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                     class="text-xl text-greyhound py-2"/>
                                </td>
                            </template>
                        </tr>
                    </template>

                    <!-- Services -->
                    <tr class="bg-greyhound">
                        <td colspan="5" class="text-lg text-white font-subheader uppercase">Services</td>
                    </tr>
                    <template v-if="!('services' in results)">
                        <tr>
                            <td colspan="5" class="text-center">
                                <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                 class="text-6xl text-greyhound py-5"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="Object.keys(results?.combined_services || {}).length > 0">
                        <tr v-for="(row, name) in results.combined_services" :key="name" class="border-b">
                            <td>{{ name }}</td>
                            <td class="text-center">{{ row.sold_qty }}</td>
                            <td class="text-right">
                                {{ formatCurrency(row.sold_total) }}
                                <FontAwesomeIcon :icon="['fas', 'clipboard']"
                                                 class="ml-2 text-caregiver cursor-pointer inline-block"
                                                 @click="() => copyToClipboard(row.sold_total)"/>
                            </td>
                            <template v-if="'boarding_accrual' in results">
                                <td class="text-center">{{ row.used_qty || 0 }}</td>
                                <td class="text-right">{{ formatCurrency(row.used_total) }}</td>
                            </template>
                            <template v-else>
                                <td colspan="2" class="text-center">
                                    <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                     class="text-xl text-greyhound"/>
                                </td>
                            </template>
                        </tr>
                    </template>

                    <!-- Other -->
                    <tr class="bg-greyhound">
                        <td colspan="5" class="text-lg text-white font-subheader uppercase">Other</td>
                    </tr>
                    <template v-if="!('tips' in results)">
                        <tr>
                            <td colspan="5" class="text-center">
                                <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                 class="text-6xl text-greyhound py-5"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr v-if="results?.tips" class="border-b">
                            <td>Tips Payable</td>
                            <td class="text-center">{{ results.tips.qty }}</td>
                            <td class="text-right">
                                {{ formatCurrency(results.tips.total) }}
                                <FontAwesomeIcon :icon="['fas', 'clipboard']"
                                                 class="ml-2 text-caregiver cursor-pointer inline-block"
                                                 @click="() => copyToClipboard(results.tips.total)"/>
                            </td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </template>

                    <!-- Packages -->
                    <tr class="bg-greyhound">
                        <td colspan="5" class="text-lg text-white font-subheader uppercase">Packages</td>
                    </tr>
                    <template v-if="!('packages' in results)">
                        <tr>
                            <td colspan="5" class="text-center">
                                <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                 class="text-6xl text-greyhound py-5"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="Object.keys(results?.combined_packages || {}).length > 0">
                        <tr v-for="(row, name) in results.combined_packages" :key="name" class="border-b">
                            <td>{{ stripLocation(name) }}</td>
                            <td class="text-center">{{ row.sold_qty }}</td>
                            <td class="text-right">
                                {{ formatCurrency(row.sold_total) }}
                                <FontAwesomeIcon :icon="['fas', 'clipboard']"
                                                 class="ml-2 text-caregiver cursor-pointer inline-block"
                                                 @click="() => copyToClipboard(row.sold_total)"/>
                            </td>
                            <template v-if="'boarding_accrual' in results">
                                <td class="text-center">{{ row.used_qty }}</td>
                                <td class="text-right">{{ formatCurrency(row.used_total) }}</td>
                            </template>
                            <template v-else>
                                <td colspan="2" class="text-center">
                                    <FontAwesomeIcon :icon="['fas', 'spinner-third']" spin
                                                     class="text-xl text-greyhound py-2"/>
                                </td>
                            </template>
                        </tr>
                    </template>
                    <tr v-else class="border-b">
                        <td colspan="5">No packages found for the specified date.</td>
                    </tr>

                    </tbody>
                </table>
            </div>

            <table v-if="results?.cash_transactions && Object.keys(results.cash_transactions).length > 0"
                   class="w-2/3 max-w-full min-w-0 table-auto bg-white rounded-lg mt-8">
                <thead>
                <tr class="bg-greyhound">
                    <td colspan="4" class="text-lg text-white font-subheader uppercase px-4 py-2">Cash Transactions</td>
                </tr>
                <tr class="bg-greyhound border-b text-sm text-white font-subheader uppercase whitespace-nowrap">
                    <th class="px-4 py-2 text-center">Invoice</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(data, invoiceId) in results.cash_transactions" :key="invoiceId" class="border-b">
                    <td class="text-center">{{ invoiceId }}</td>
                    <td>{{ data.date }}</td>
                    <td>{{ data.owner }}</td>
                    <td class="text-right">{{ formatCurrency(data.amount) }}</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
td {
    padding: 2px 4px 2px 4px;
    white-space: nowrap;
}
</style>

<script setup>
import {ref} from 'vue'
import axios from 'axios'

const date = ref(new Date(Date.now() - 86400000).toLocaleDateString('en-CA'));
const bankAccount = ref('1110 Operating Account'); // matches JournalEntryTransformer::DEFAULT_BANK_ACCOUNT
const csvFile = ref(null);
const errorMessage = ref(null);
const loading = ref(false);
const result = ref(null);

function formatCurrency(value) {
    if (value === '' || value === null || value === undefined) return '';
    return new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(value);
}

function handleFileChange(e) {
    csvFile.value = e.target.files[0] ?? null;
}

async function handleSubmit() {
    loading.value = true;
    errorMessage.value = null;
    result.value = null;

    const formData = new FormData();
    formData.append('date', date.value);
    formData.append('bank_account', bankAccount.value);
    formData.append('csv', csvFile.value);

    try {
        const response = await axios.post('/journalmaker/transform', formData, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'multipart/form-data',
            },
        });
        result.value = response.data;
    } catch (error) {
        if (error.response?.status === 419) {
            if (confirm('Your session has expired. Reload the page?')) window.location.reload();
        } else {
            errorMessage.value = error.response?.data?.message || error.message || 'Server error';
        }
    } finally {
        loading.value = false;
    }
}

function downloadCsv() {
    if (!result.value) return;

    const headers = ['JournalNo', 'JournalDate', 'Currency', 'Account', 'Debits', 'Credits', 'Description', 'Name'];
    const dataRows = result.value.rows.map(r => [
        r.JournalNo, r.JournalDate, r.Currency, r.Account,
        r.Debits, r.Credits, r.Description, r.Name,
    ]);

    const csv = [headers, ...dataRows]
        .map(row => row.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], {type: 'text/csv'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `GINGR-JE-${date.value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <div class="flex items-center justify-center min-h-screen bg-greyhound">
        <div class="w-full max-w-full flex flex-col items-center">
            <div class="w-1/2 max-w-full min-w-0 p-8 space-y-8 bg-white shadow-md rounded-lg">
                <h2 class="text-2xl font-header text-center">Gingr Journal Maker</h2>
                <div class="bg-sunshine border-l-4 p-4 mt-4 rounded">
                    <p class="font-medium">Important:</p>
                    <p>This tool is for novelty purposes only. It was not created or supported by Camp Bow Wow or
                        Propelled Brands; contact a franchisee with questions. Your login information is not saved and
                        the data is not collected in any way.</p>
                </div>
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div>
                        <label for="bank_account" class="block font-medium text-greyhound">QBO Checking Account Name</label>
                        <input v-model="bankAccount" type="text" id="bank_account" required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>
                    <div>
                        <label for="date" class="block font-medium text-greyhound">Report Date</label>
                        <input v-model="date" type="date" id="date" required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>
                    <div>
                        <label for="csv" class="block font-medium text-greyhound">charges_by_account_code.csv</label>
                        <input @change="handleFileChange" type="file" id="csv" accept=".csv,text/csv" required
                               class="w-full mt-1 px-4 py-2 border border-greyhound rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver focus:border-caregiver"/>
                    </div>

                    <div v-if="errorMessage" class="p-4 bg-alerted border rounded">
                        {{ errorMessage }}
                    </div>

                    <button type="submit" :disabled="loading"
                            class="w-full px-4 py-2 text-white bg-caregiver rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <FontAwesomeIcon v-if="loading" :icon="['fas', 'spinner-third']" spin class="mr-2"/>
                        Generate Journal Entry
                    </button>
                </form>
            </div>

            <div v-if="result" class="w-2/3 max-w-full min-w-0 mt-8 p-8 bg-white shadow-md rounded-lg">
                <div v-if="result.warnings?.length" class="mb-6 space-y-2">
                    <div v-for="(w, i) in result.warnings" :key="i"
                         class="p-3 bg-yellow-50 border border-yellow-300 rounded text-yellow-800 text-sm">
                        <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']" class="mr-2"/>{{ w }}
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-subheader uppercase">GINGR-{{ date }}</h3>
                    <button @click="downloadCsv"
                            class="px-4 py-2 bg-caregiver text-white text-sm rounded shadow">
                        Download CSV
                    </button>
                </div>

                <table class="min-w-full table-auto bg-white rounded-lg">
                    <thead>
                    <tr class="bg-greyhound border-b text-white font-subheader uppercase text-sm">
                        <th class="px-4 py-2 text-left">Account</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2 text-left">Description</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(row, i) in result.rows" :key="i" class="border-b"
                        :class="{'font-semibold': row.Debits !== ''}">
                        <td class="px-4 py-2">{{ row.Account }}</td>
                        <td class="px-4 py-2 text-right">{{ row.Debits !== '' ? formatCurrency(row.Debits) : '' }}</td>
                        <td class="px-4 py-2 text-right">{{ row.Credits !== '' ? formatCurrency(row.Credits) : '' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ row.Description }}</td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr class="border-t-2 border-greyhound font-semibold">
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right">{{ formatCurrency(result.total) }}</td>
                        <td class="px-4 py-2 text-right">{{ formatCurrency(result.total) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
td, th {
    white-space: nowrap;
}
</style>

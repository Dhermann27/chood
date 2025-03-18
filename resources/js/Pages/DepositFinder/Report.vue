<script setup>
import {ref, computed} from 'vue'
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
const daycarePackageTotal = computed(() => {
    let total = 0.0;
    if (results.value && results.value.packages) {
        for (const [key, result] of Object.entries(results.value?.packages)) {
            if (key.includes("Day Camp") || key.includes("First Day")) total += result.total;
        }
    }
    return total;
});
const trainingPackagesTotal = computed(() => {
    let total = 0.0;
    if (results.value && results.value.packages) {
        for (const [key, result] of Object.entries(results.value?.packages)) {
            if (key.includes("Train")) total += result.total;
        }
    }
    return total;
});


function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

// Function to handle login and fetch cookies
const handleLogin = async () => {
    started.value = true;
    results.value = [];
    errorMessage.value = null;
    try {
        const response = await axios.post('/depositfinder/login',
            {
                username: username.value,
                password: password.value,
                date: date.value
            },
            {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                }
            }
        );

        results.value = response.data;
        pollResults(response.data.id);
    } catch (error) {
        if (error.response && error.response.status === 419) {
            started.value = false;
            if (confirm('Your session has expired due to inactivity. Would you like to reload the page?')) {
                window.location.reload();
            }
        } else if (error.response && error.response.data) {
            started.value = false;
            errorMessage.value = error.response?.data?.output || 'Login failed due to server error';
            console.error('Error response:', error.response);
        } else {
            started.value = false;
            errorMessage.value = 'Network error or no response from server';
            console.error('Network error:', error);
        }
    }
}

function pollResults(reportId) {
    let pollInterval;
    try {
        pollInterval = setInterval(async () => {
            try {
                const response = await axios.get('/depositfinder/results/' + reportId);

                results.value = response.data.data;
                if ('services' in results.value) clearInterval(pollInterval);

            } catch (error) {
                errorMessage.value = error.response?.data?.output || 'Error fetching results';
                clearInterval(pollInterval);
            }
        }, 3000);
    } catch (error) {
        errorMessage.value = error.response?.data?.output || 'Error when results fetched';
        clearInterval(pollInterval);
    }
}

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        // console.log('Text copied to clipboard');
    } catch (err) {
        // console.error('Failed to copy text: ', err);
    }
};

</script>

<template>
    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="w-full max-w-full flex flex-col items-center">
            <!-- Form Container -->
            <div class="w-1/2 max-w-full min-w-0 p-8 space-y-8 bg-white shadow-md rounded-lg">
                <h2 class="text-2xl font-bold text-center text-gray-800">Daily Deposit Finder</h2>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-4 rounded">
                    <p class="font-medium">Important:</p>
                    <p>This tool is for novelty purposes only. It was not created or supported by Camp Bow Wow or
                        Propelled Brands; contact a franchisee with questions. Your login information is not saved and
                        the data is not collected in any way.</p>
                </div>
                <form @submit.prevent="handleLogin" class="space-y-6">
                    <div>
                        <label for="username" class="block font-medium text-gray-700">Data Dawg Username</label>
                        <input
                            v-model="username"
                            type="text"
                            id="username"
                            class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Enter your username"
                            required
                        />
                    </div>

                    <div>
                        <label for="password" class="block font-medium text-gray-700">Data Dawg Password</label>
                        <input
                            v-model="password"
                            type="password"
                            id="password"
                            class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Enter your password"
                            required
                        />
                    </div>

                    <div>
                        <label for="date" class="block font-medium text-gray-700">Date</label>
                        <input
                            v-model="date"
                            type="date"
                            id="date"
                            class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            required
                        />
                    </div>

                    <div v-if="errorMessage" class="p-4 mb-4 bg-red-100 border border-red-500 text-red-700 rounded">
                        {{ errorMessage }}
                    </div>

                    <div>
                        <button
                            type="submit"
                            class="w-full px-4 py-2 text-white bg-indigo-600 rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="results && started"
                 class="w-2/3 max-w-full min-w-0 mt-8 p-8 bg-white shadow-md rounded-lg">
                <h3 class="text-xl font-bold mb-4">Date: {{ results?.report_date ?? date }}</h3>
                <table class="min-w-full table-auto bg-white rounded-lg">
                    <thead>
                    <tr class="bg-gray-100 border-b text-left font-semibold text-gray-600">
                        <th class="px-4 py-2">Category</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Quantity</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Total Amount</th>
                    </tr>
                    </thead>
                    <tbody>

                    <tr class="bg-gray-200">
                        <td colspan="3" class="text-lg font-semibold">Daily Deposit</td>
                    </tr>
                    <template v-if="!('deposits' in results)">
                        <tr>
                            <td colspan="3" class="text-center">
                                <font-awesome-icon :icon="['fas', 'spinner-third']" spin
                                                   class="text-6xl text-gray-600"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="Object.keys(results?.deposits).length > 0">
                        <tr v-for="(result, key) in results.deposits" :key="key"
                            class="border-b hover:bg-gray-50">
                            <td>{{ key }}</td>
                            <td>{{ result.qty }}</td>
                            <td>
                                {{ formatCurrency(result.total) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(result.total)"
                                />
                            </td>
                        </tr>
                    </template>
                    <tr v-else class="border-b hover:bg-gray-50">
                        <td colspan="3">
                            No deposits found for the specified date range.
                        </td>
                    </tr>


                    <tr class="bg-gray-200 text-gray-700">
                        <td colspan="3" class="text-lg font-semibold">Packages Sold</td>
                    </tr>
                    <template v-if="!('packages' in results)">
                        <tr>
                            <td colspan="3" class="text-center">
                                <font-awesome-icon :icon="['fas', 'spinner-third']" spin
                                                   class="text-6xl text-gray-600"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="Object.keys(results?.packages).length > 0">
                        <tr v-for="(result, key) in results.packages" :key="key"
                            class="border-b hover:bg-gray-50">
                            <td>{{ key }}</td>
                            <td>{{ result.qty }}</td>
                            <td>{{ formatCurrency(result.total) }}</td>
                        </tr>
                    </template>
                    <tr v-else class="border-b hover:bg-gray-50">
                        <td colspan="3">
                            No packages found for the specified date range.
                        </td>
                    </tr>


                    <tr class="bg-gray-200">
                        <td colspan="3" class="text-lg font-semibold">Services Sold</td>
                    </tr>
                    <template v-if="!('services' in results)">
                        <tr>
                            <td colspan="3" class="text-center">
                                <font-awesome-icon :icon="['fas', 'spinner-third']" spin
                                                   class="text-6xl text-gray-600"/>
                            </td>
                        </tr>
                    </template>
                    <template v-else-if="Object.keys(results?.services).length > 0">
                        <tr v-for="(result, key) in results.services" :key="key"
                            class="border-b hover:bg-gray-50">
                            <td>{{ key }}</td>
                            <td>{{ result.qty }}</td>
                            <td>
                                {{ formatCurrency(result.total) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(result.total)"
                                />
                                <div v-if="key === 'Day Care'" class="text-sm italic">
                                    w/Packages: {{ formatCurrency(result.total + daycarePackageTotal) }}
                                    <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                       @click="() => copyToClipboard(result.total + daycarePackageTotal)"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="trainingPackagesTotal > 0.0" class="border-b hover:bg-gray-50">
                            <td>Training</td>
                            <td>N/A</td>
                            <td>
                                {{ formatCurrency(trainingPackagesTotal) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(trainingPackagesTotal)"
                                />
                            </td>
                        </tr>
                        <tr v-if="results?.tips" class="border-b hover:bg-gray-50">
                            <td>Tips Payable</td>
                            <td>{{ results.tips.qty }}</td>
                            <td>{{ formatCurrency(results.tips.total) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(trainingPackagesTotal)"
                                />
                            </td>
                        </tr>
                        <tr v-if="results?.product" class="border-b hover:bg-gray-50">
                            <td>Retail Products</td>
                            <td>{{ results.product.qty }}</td>
                            <td>{{ formatCurrency(results.product.total) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(trainingPackagesTotal)"
                                />
                            </td>
                        </tr>
                        <tr v-if="results?.tax" class="border-b hover:bg-gray-50">
                            <td>Sales Tax to Pay</td>
                            <td>N/A</td>
                            <td>{{ formatCurrency(results.tax.total) }}
                                <font-awesome-icon :icon="['fas', 'clipboard']" class="cursor-pointer text-blue-500"
                                                   @click="() => copyToClipboard(trainingPackagesTotal)"
                                />
                            </td>
                        </tr>
                    </template>
                    <tr v-else class="border-b hover:bg-gray-50">
                        <td colspan="3">
                            No services found for the specified date range.
                        </td>
                    </tr>
                    </tbody>
                </table>

                <table v-if="results?.cash && Object.keys(results?.cash).length > 0"
                       class="min-w-full table-auto bg-white rounded-lg mt-5">
                    <thead>
                    <tr class="bg-gray-200">
                        <td colspan="6" class="font-semibold">Cash Transactions</td>
                    </tr>
                    <tr class="bg-gray-100 border-b text-left font-semibold text-gray-600">
                        <th class="px-4 py-2">Order Id</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Date</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">First Name</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Last Name</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Items</th>
                        <th class="px-4 py-2 text-left font-semibold text-gray-600">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(data, orderId) in results.cash" :key="orderId"
                        class="border-b hover:bg-gray-50">
                        <td>{{ orderId }}</td>
                        <td>{{ data.date }}</td>
                        <td>{{ data.firstName }}</td>
                        <td>{{ data.lastName }}</td>
                        <td>{{ data.items }}</td>
                        <td>{{ formatCurrency(data.amount) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
<style scoped>
td {
    padding: 2px 4px 2px 4px;
    color: rgb(55, 65, 81)
}
</style>

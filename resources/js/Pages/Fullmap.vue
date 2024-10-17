<script setup>
import {Head, Link} from '@inertiajs/vue3';
import {ref, onMounted, onBeforeUnmount} from 'vue';

const props = defineProps({
    photoUri: {
        type: String,
    },
    cabins: {
        type: Object,
    },
    dogs: {
        type: Object,
    },
    checksum: {
        type: String,
    }
});

const dogs = ref({});
let local_checksum = ref(props.checksum);
let refreshInterval = null;

const fetchData = async () => {
    try {
        const response = await fetch('/api/fullmap/' + local_checksum.value);
        const newData = await response.json();
        if (newData) {
            local_checksum.value = newData.checksum;
            dogs.value = newData.dogs;
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

const isBoarder = (services) => {
    if (services) return services.some(service => service.id === 1003 || service.id === 1004);
    return false;
}

// Fetch data when the component is mounted
onMounted(() => {
    dogs.value = props.dogs;
    refreshInterval = setInterval(fetchData, 5000); // Refresh data every 5 seconds
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Fullmap"/>
    <div class="bg-gray-90 text-black/50 ">
        <div
            class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
            <div class="relative w-full px-6 max-w-full">
                <main>
                    <div class="w-full h-screen choodmap">
                        <div v-for="cabin in cabins"
                             :class="'cabin ' + (dogs[cabin.id] ? (isBoarder(dogs[cabin.id].services) ? 'cabin-boarder' : 'cabin-daycamper') : 'cabin-empty')"
                             :style="{ gridRow: cabin.row + ' / span ' + cabin.rowspan,  gridColumn: cabin.column,
                              backgroundImage: dogs[cabin.id] ? 'url(' + props.photoUri + dogs[cabin.id].photoUri + ')' : 'none',
                               }">

                            <div v-if="dogs[cabin.id]">
                                <div class="dog-banner">
                                    <div v-if="isBoarder(dogs[cabin.id].services)" style="background-color: #44687d;">Boarder</div>
                                    <div v-else style="background-color: #50C878;">Daycamper</div>
                                </div>
                                <div class="dog-name">{{ dogs[cabin.id].name }}</div>
                            </div>
                            <div v-else>{{ cabin.cabinName }}</div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.choodmap {
    display: grid;
    grid-template-columns: 1fr 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px repeat(2, 1fr) 20px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}

.cabin {
    background-color: #f0f0f0;
    border: 2px solid black;
    overflow: hidden;
}

.cabin-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3vw;
    color: black;
    overflow: hidden;
}

.cabin-boarder {
    position: relative;
    font-size: 1.5vw;
    background-size: cover;
    background-position: center;
    color: #44687d;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.7);
}

.cabin-daycamper {
    position: relative;
    font-size: 1.5vw;
    background-size: cover;
    background-position: center;
    color: #50C878;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.7);
}

.dog-name {
    position: absolute; /* Position the text absolutely */
    bottom: 0; /* Align to the bottom */
    left: 0; /* Align to the left */
    margin: 0; /* Remove default margin */

}

.dog-banner {
    width: 100%; /* Full width */
    font-size: 1vw;
    line-height: 1vw;         /* Align text vertically within the div */
    padding: 0;
    color: white; /* Text color */
    text-align: center; /* Center the text */
    overflow: hidden; /* Hide overflow if any */
    position: absolute; /* Position the banner absolutely */
    top: 0; /* Align at the top */
    left: 0; /* Align at the left */
}

</style>

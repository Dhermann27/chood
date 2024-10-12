<script setup>
import {Head, Link} from '@inertiajs/vue3';
import ApplicationLogo from "@/Components/ApplicationLogo.vue";

const {dogList, photoUri, cabins} = defineProps({
    dogList: {
        type: Array,
    },
    photoUri: {
        type: String,
    },
    rows: {
        type: Array,
    },
    columns: {
        type: Array,
    },
    cabins: {
        type: Object,
    },
});

const isMissingCell = (row, column) => {
    return cabins[row - 1] && cabins[row - 1][column] && cabins[row - 1][column].rowspan === 2;
};

const getBackgroundStyle = (row, column) => {
    if (cabins[row] && cabins[row][column] && dogList[cabins[row][column].id] && dogList[cabins[row][column].id].photoUri) {
        return {
            backgroundImage: `url(` + photoUri + dogList[cabins[row][column].id].photoUri + `)`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            color: 'white',
            textShadow: '2px 2px 4px rgba(0, 0, 0, 0.7)',
            alignItems: 'flex-end'
        };
    } else {
        return '';
    }
}
const getCabinClass = (row, column) => {
    var css = "";
    if (cabins[row] && cabins[row][column]) {
        css = "mapitem";
        if (cabins[row][column].rowspan === 2) {
            css += " mapitem-span2"
        }
    }
    return css;
};

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
                        <template v-for="row in rows">
                            <template v-for="column in columns">
                                <div v-if="!isMissingCell(row, column)" :style="getBackgroundStyle(row, column)"
                                     :class="getCabinClass(row, column)">
                                    <div v-if="cabins[row] && cabins[row][column]">
                                        <div v-if="dogList[cabins[row][column].id]" class="text-lg text-left">
                                            {{ dogList[cabins[row][column].id].name }}
                                        </div>
                                        <div v-else>
                                            {{ cabins[row][column].cabinName }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>

                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.choodmap {
    display: grid;
    grid-template-columns: 1fr 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px repeat(2, 1fr) 10px 1fr;
    grid-template-rows: repeat(4, 1fr) 10px repeat(5, 1fr);
    padding: 5px;
}

.mapitem {
    background-color: #f0f0f0;
    border: 2px solid #ccc;
    display: flex; /* Center content */
    align-items: center;
    justify-content: center;
    font-size: 3vw; /* Adjust this value as necessary */
    overflow: hidden; /* Hide overflow if text is too large */
}


/* Span two rows */
.mapitem-span2 {
    grid-row: span 2; /* Span 2 rows */
}

</style>

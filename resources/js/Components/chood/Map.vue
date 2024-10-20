<script setup>
import {Head, Link} from '@inertiajs/vue3';

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

const isBoarder = (services) => {
    if (services) return services.some(service => service.id === 1003 || service.id === 1004);
    return false;
}

const cabinClass = (cabin) => {
    if (props.dogs[cabin.id]) {
        return isBoarder(props.dogs[cabin.id].services)
            ? 'cabin cabin-boarder'
            : 'cabin cabin-daycamper';
    }
    return 'cabin cabin-empty';
};

const cabinStyle = (cabin) => {
    const dog = props.dogs[cabin.id];
    return {
        gridRow: `${cabin.row} / span ${cabin.rowspan}`,
        gridColumn: cabin.column,
        backgroundImage: dog
            ? `url(${props.photoUri}${dog.photoUri})`
            : 'none',
        borderColor: cabin.cleaning_status
            ? cabin.cleaning_status.cleaning_type === 'deep'
                ? '#dd454f'
                : '#f4df7a'
            : '#373a36',
    };
};
</script>
<template>
    <div
        v-for="cabin in cabins"
        :key="cabin.id"
        :class="cabinClass(cabin)"
        :style="cabinStyle(cabin)"
    >
        <div v-if="dogs[cabin.id]">
            <div class="dog-banner">
                <div v-if="isBoarder(dogs[cabin.id].services)" class="boarder-banner">Boarder</div>
                <div v-else class="daycamper-banner">Daycamper</div>
            </div>
            <div class="dog-name">{{ dogs[cabin.id].name }}</div>
        </div>
        <div v-else>{{ cabin.cabinName }}</div>
    </div>
</template>

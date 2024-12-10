<script setup>
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    photoUri: String,
    cabins: Object,
    dogs: Object,
    maxlength: Number,
    checksum: String
});

const isBoarder = (services) => {
    if (services) return services.some(service => service.id === 1003 || service.id === 1004);
    return false;
}

const cabinStyle = (cabin) => {
    return {
        gridRow: `${cabin.rho} / span ${cabin.rowspan}`,
        gridColumn: cabin.kappa,
        borderColor: cabin.cleaning_status
            ? cabin.cleaning_status.cleaning_type === 'deep'
                ? '#dd454f'
                : '#f4df7a'
            : '#373a36'
    };
};
</script>
<template>
    <div
        v-for="cabin in cabins"
        :key="cabin.id"
        :class="{ 'cabin-empty': !props.dogs[cabin.id] }"
        :style="cabinStyle(cabin)"
    >
        <div v-if="dogs[cabin.id]" class="h-full w-full">
            <DogCard :dog="dogs[cabin.id]" :photoUri="props.photoUri" :maxlength="maxlength"/>
        </div>
        <div v-else>{{ cabin.cabinName }}</div>
    </div>
</template>

<script setup>
const props = defineProps({
    sectionCounts: {type: Object, default: () => ({checkin_today: null, checkout_today: null})},
    fontSize: {type: Number, required: true},
});
</script>

<template>
    <span v-if="sectionCounts?.checkin_today !== null" class="flex items-center gap-1 leading-none font-bold"
          :style="{ fontSize: fontSize + 'px' }">
        <template v-if="sectionCounts.in_house != null">{{ sectionCounts.in_house }}</template>
        {{ sectionCounts.checkin_today }}
        <FontAwesomeIcon :icon="['fas', 'arrows-left-right']" style="transform: translateY(-0.1em)"/>
        {{ sectionCounts.checkout_today }}
    </span>
</template>

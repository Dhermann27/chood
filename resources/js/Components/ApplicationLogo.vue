<template>
    <div class="logo-container">
        <img src="/images/logo.png" alt="Camp Bow Wow Logo" class="logo-image">
    </div>
</template>

<script>
export default {
    name: 'Logo',
};
</script>

<style scoped>
.logo-image {
    max-width: 100%; /* Ensure the logo image does not exceed its container's width */
    height: auto; /* Automatically adjust height while maintaining aspect ratio */
}
</style>

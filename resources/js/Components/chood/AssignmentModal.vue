<script setup>
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    modalType: String,
    cabins: Array,
    outhouseDogs: Array,
    isNewDog: Boolean,
    services: Array,
    assignment: Object,
    errorMessages: Array,
    photoUri: String,
});

const emit = defineEmits(['closeModal', 'submitForm', 'updateIsNewDog']);

const toggleNewDog = () => {
    emit('updateIsNewDog', !props.isNewDog);
};

const submitForm = () => {
    emit('submitForm', props.assignment);
};

const closeModal = () => {
    emit('closeModal');
};
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h3 class="text-lg font-semibold mb-4">
                {{ (modalType === 'add' ? 'Add ' : 'Edit ') }} Cabin Assignment
            </h3>

            <form @submit.prevent="submitForm">
                <div class="mb-4">
                    <label for="cabin-select">Select Cabin</label>
                    <select id="cabin-select" v-model="props.assignment.cabin_id" required
                            class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2">
                        <option disabled value="">Please select a cabin</option>
                        <option v-for="cabin in cabins" :key="cabin.id" :value="cabin.id">
                            {{ cabin.cabinName }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center space-x-4">
                    <div @click="toggleNewDog" class="cursor-pointer text-3xl">
                        <font-awesome-icon
                            :icon="props.isNewDog ? ['fas', 'square-check'] : ['far', 'square']"
                            class="text-blue-600 hover:text-blue-800"
                        />
                    </div>
                    <label class="text-lg font-medium">Check for future reservation</label>
                </div>


                <template v-if="!props.isNewDog">
                    <!-- Dog Selection -->
                    <div class="mb-4">
                        <multiselect
                            v-model="assignment.dogs" multiple
                            :options="outhouseDogs"
                            label="firstname"
                            :searchable="true"
                            :clearable="true"
                            placeholder="Select Dog(s)"
                        >
                            <template #option="props">
                                <div v-if="props.option.id === 'new'" class="text-sm text-blue-500">
                                    Enter a new dog
                                </div>
                                <div v-else>
                                    <img v-if="props.option.photoUri" :src="photoUri + props.option.photoUri"
                                         :alt="'Picture of' + props.option.firstname" class="dog-photo"/>
                                    {{ props.option.firstname }}
                                </div>
                            </template>
                        </multiselect>
                    </div>
                </template>
                <template v-else>
                    <div class="mb-4">
                        <label for="name" class="block text-xs font-medium text-gray-700">Dog Name</label>
                        <input
                            v-model="assignment.firstname"
                            id="firstname"
                            type="text"
                            class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="name" class="block text-xs font-medium text-gray-700">Family Name</label>
                        <input
                            v-model="assignment.lastname"
                            id="lastname"
                            type="text"
                            class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2"
                        />
                    </div>

                    <!-- Services (Multiselect) -->
                    <div class="mb-4">
                        <label for="service_ids" class="block text-xs font-medium text-gray-700">Services</label>
                        <Multiselect
                            v-model="assignment.services"
                            :options="services"
                            multiple
                            track-by="id"
                            label="name"
                            placeholder="Select services"
                            class="w-full text-sm"
                        />
                    </div>
                </template>

                <div v-if="errorMessages.length > 0"
                     class="p-4 mb-4 bg-red-100 border border-red-500 text-red-700 rounded">
                    <div v-for="message in errorMessages" :key="message" class="font-semibold">
                        {{ message }}
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" @click="closeModal"
                            class="px-4 py-2 bg-gray-400 text-white rounded-md text-xs hover:bg-gray-500">Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700">
                        {{ modalType === 'add' ? 'Add Assignment' : 'Update Assignment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.dog-photo {
    width: 30px;
    height: 30px;
    margin: 0 5px 5px 0;
    border-radius: 10%;
}
</style>


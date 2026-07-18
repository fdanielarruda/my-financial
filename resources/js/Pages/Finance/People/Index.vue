<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    people: Array,
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    color: '#2563eb',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(person) {
    editing.value = person;
    form.name = person.name;
    form.color = person.color;
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showModal.value = false) };

    if (editing.value) {
        form.put(route('finance.people.update', editing.value.id), options);
    } else {
        form.post(route('finance.people.store'), options);
    }
}

function destroy(person) {
    if (confirm(`Remover "${person.name}"? Isso só é possível se não houver contas vinculadas.`)) {
        useForm({}).delete(route('finance.people.destroy', person.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Pessoas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Pessoas</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <PrimaryButton @click="openCreate">Nova pessoa</PrimaryButton>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="person in people"
                            :key="person.id"
                            class="flex items-center justify-between px-4 py-4 sm:px-6"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: person.color }"
                                />
                                <span class="font-medium text-gray-900">{{ person.name }}</span>
                                <span class="text-sm text-gray-500">{{ person.accounts_count }} conta(s)</span>
                            </div>
                            <div class="flex gap-3">
                                <button class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(person)">
                                    Editar
                                </button>
                                <button class="text-sm text-red-600 hover:text-red-900" @click="destroy(person)">
                                    Remover
                                </button>
                            </div>
                        </li>
                        <li v-if="people.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhuma pessoa cadastrada ainda.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Editar pessoa' : 'Nova pessoa' }}
                </h2>

                <div class="mt-6">
                    <InputLabel for="name" value="Nome" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="color" value="Cor" />
                    <input id="color" v-model="form.color" type="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300" />
                    <InputError class="mt-2" :message="form.errors.color" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

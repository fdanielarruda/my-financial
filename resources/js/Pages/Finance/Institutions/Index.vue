<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    institutions: Array,
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    color: '#64748b',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(institution) {
    editing.value = institution;
    form.name = institution.name;
    form.color = institution.color;
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showModal.value = false) };

    if (editing.value) {
        form.put(route('finance.institutions.update', editing.value.id), options);
    } else {
        form.post(route('finance.institutions.store'), options);
    }
}

function destroy(institution) {
    if (confirm(`Remover "${institution.name}"?`)) {
        useForm({}).delete(route('finance.institutions.destroy', institution.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Instituições" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Instituições financeiras</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <PrimaryButton @click="openCreate">Nova instituição</PrimaryButton>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="institution in institutions"
                            :key="institution.id"
                            class="flex items-center justify-between px-4 py-4 sm:px-6"
                        >
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: institution.color }" />
                                <span class="font-medium text-gray-900">{{ institution.name }}</span>
                                <span v-if="!institution.user_id" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                    padrão
                                </span>
                            </div>
                            <div v-if="institution.user_id" class="flex gap-3">
                                <button class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(institution)">
                                    Editar
                                </button>
                                <button class="text-sm text-red-600 hover:text-red-900" @click="destroy(institution)">
                                    Remover
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Editar instituição' : 'Nova instituição' }}
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

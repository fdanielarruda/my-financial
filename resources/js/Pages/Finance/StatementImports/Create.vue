<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    accounts: Array,
});

const form = useForm({
    account_id: '',
    file: null,
});

function handleFile(event) {
    form.file = event.target.files[0] ?? null;
}

function submit() {
    form.post(route('finance.statement-imports.upload'), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Importar fatura" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Importar fatura (PDF)</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <p class="text-sm text-gray-600">
                        Envie o PDF da fatura do cartão de crédito. A OpenAI vai ler o documento e extrair os
                        lançamentos, incluindo compras parceladas — as parcelas futuras já são calculadas
                        automaticamente. Você poderá revisar e ajustar tudo antes de confirmar a importação.
                    </p>

                    <form class="mt-6 space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="account_id" value="Cartão de crédito" />
                            <SelectInput id="account_id" v-model="form.account_id" class="mt-1 block w-full">
                                <option value="" disabled>Selecione o cartão</option>
                                <option v-for="account in accounts" :key="account.id" :value="account.id">
                                    {{ account.name }}
                                </option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.account_id" />
                            <p v-if="accounts.length === 0" class="mt-2 text-sm text-amber-600">
                                Nenhum cartão de crédito cadastrado ainda.
                            </p>
                        </div>

                        <div>
                            <InputLabel for="file" value="Arquivo PDF da fatura" />
                            <input
                                id="file"
                                type="file"
                                accept="application/pdf"
                                class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700"
                                @change="handleFile"
                            />
                            <InputError class="mt-2" :message="form.errors.file" />
                        </div>

                        <div v-if="form.processing" class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-700">
                            <div v-if="form.progress && form.progress.percentage < 100">
                                Enviando arquivo... {{ form.progress.percentage }}%
                            </div>
                            <div v-else>
                                Lendo a fatura com a OpenAI, isso pode levar até um minuto...
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing || !form.account_id || !form.file">
                                Enviar e analisar
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

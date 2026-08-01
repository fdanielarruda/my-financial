<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    transfers: Object,
    accounts: Array,
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    from_account_id: props.accounts[0]?.id ?? '',
    to_account_id: props.accounts[1]?.id ?? '',
    amount: '',
    date: today,
    description: '',
});

function submit() {
    form.post(route('finance.transfers.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('amount', 'description'),
    });
}

function destroy(transfer) {
    if (confirm('Remover esta transferência?')) {
        useForm({}).delete(route('finance.transfers.destroy', transfer.id), { preserveScroll: true });
    }
}

const groupedByDay = computed(() => {
    const groups = new Map();

    for (const transfer of props.transfers.data) {
        const key = transfer.date.slice(0, 10);

        if (!groups.has(key)) {
            groups.set(key, { date: key, transfers: [] });
        }

        groups.get(key).transfers.push(transfer);
    }

    return [...groups.values()].sort((a, b) => b.date.localeCompare(a.date));
});
</script>

<template>
    <Head title="Transferências" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Transferências</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-5 shadow sm:rounded-lg">
                    <h3 class="font-medium text-gray-900">Nova transferência</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Use para mover dinheiro entre suas contas: aporte/resgate de investimento, pagamento entre contas, etc.
                    </p>
                    <form class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3" @submit.prevent="submit">
                        <div>
                            <InputLabel for="from_account_id" value="De" />
                            <SelectInput id="from_account_id" v-model="form.from_account_id" class="mt-1 block w-full">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.from_account_id" />
                        </div>

                        <div>
                            <InputLabel for="to_account_id" value="Para" />
                            <SelectInput id="to_account_id" v-model="form.to_account_id" class="mt-1 block w-full">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.to_account_id" />
                        </div>

                        <div>
                            <InputLabel for="amount" value="Valor" />
                            <TextInput id="amount" v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.amount" />
                        </div>

                        <div>
                            <InputLabel for="date" value="Data" />
                            <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.date" />
                        </div>

                        <div class="col-span-2">
                            <InputLabel for="description" value="Descrição (opcional)" />
                            <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div class="col-span-2 flex items-end sm:col-span-3">
                            <PrimaryButton :disabled="form.processing">Transferir</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <div v-for="group in groupedByDay" :key="group.date">
                        <div class="bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                            {{ formatDate(group.date) }}
                        </div>
                        <ul class="divide-y divide-gray-200">
                            <li
                                v-for="transfer in group.transfers"
                                :key="transfer.id"
                                class="flex items-center justify-between px-4 py-3 sm:px-6"
                            >
                                <div>
                                    <p class="text-gray-900">
                                        {{ transfer.description || 'Transferência' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ transfer.from_account.name }} → {{ transfer.to_account.name }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-900">{{ formatMoney(transfer.amount) }}</span>
                                    <button class="text-sm text-red-600 hover:text-red-900" @click="destroy(transfer)">
                                        Remover
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <p v-if="transfers.data.length === 0" class="px-4 py-6 text-sm text-gray-500 sm:px-6">
                        Nenhuma transferência ainda.
                    </p>

                    <div v-if="transfers.links.length > 3" class="flex flex-wrap gap-2 border-t px-4 py-3 sm:px-6">
                        <Link
                            v-for="link in transfers.links"
                            :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded px-3 py-1 text-sm"
                            :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

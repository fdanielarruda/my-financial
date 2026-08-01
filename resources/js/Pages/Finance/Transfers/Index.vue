<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    transfers: Object,
    accounts: Array,
    institutions: Array,
    filters: Object,
});

const today = new Date().toISOString().slice(0, 10);
const editing = ref(null);

const filters = reactive({
    institution_id: props.filters.institution_id ?? '',
    account_id: props.filters.account_id ?? '',
});

const accountsForFilterBank = computed(() =>
    filters.institution_id === ''
        ? props.accounts
        : props.accounts.filter((a) => a.institution?.id === Number(filters.institution_id))
);

function applyFilters() {
    router.get(route('finance.transfers.index'), filters, { preserveState: true, replace: true });
}

watch(
    () => filters.institution_id,
    () => {
        if (!accountsForFilterBank.value.some((a) => a.id === Number(filters.account_id))) {
            filters.account_id = '';
        }
    }
);

function accountBankKey(account) {
    return account.institution?.id ?? 'none';
}

function accountBankLabel(account) {
    return account.institution?.name ?? 'Dinheiro';
}

const formBanks = computed(() => {
    const seen = new Map();

    for (const a of props.accounts) {
        seen.set(accountBankKey(a), accountBankLabel(a));
    }

    return [...seen.entries()]
        .map(([key, label]) => ({ key, label }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const fromBank = ref(accountBankKey(props.accounts[0] ?? {}));
const toBank = ref(accountBankKey(props.accounts[1] ?? props.accounts[0] ?? {}));

const accountsForFromBank = computed(() => props.accounts.filter((a) => accountBankKey(a) === fromBank.value));
const accountsForToBank = computed(() => props.accounts.filter((a) => accountBankKey(a) === toBank.value));

const form = useForm({
    from_account_id: props.accounts[0]?.id ?? '',
    to_account_id: props.accounts[1]?.id ?? '',
    amount: '',
    date: today,
    description: '',
});

watch(fromBank, () => {
    if (!accountsForFromBank.value.some((a) => a.id === Number(form.from_account_id))) {
        form.from_account_id = accountsForFromBank.value[0]?.id ?? '';
    }
});

watch(toBank, () => {
    if (!accountsForToBank.value.some((a) => a.id === Number(form.to_account_id))) {
        form.to_account_id = accountsForToBank.value[0]?.id ?? '';
    }
});

function submit() {
    if (editing.value) {
        form.put(route('finance.transfers.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });

        return;
    }

    form.post(route('finance.transfers.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('amount', 'description'),
    });
}

function openEdit(transfer) {
    editing.value = transfer;
    fromBank.value = accountBankKey(transfer.from_account);
    toBank.value = accountBankKey(transfer.to_account);
    form.from_account_id = transfer.from_account.id;
    form.to_account_id = transfer.to_account.id;
    form.amount = transfer.amount;
    form.date = transfer.date.slice(0, 10);
    form.description = transfer.description ?? '';
    form.clearErrors();
}

function cancelEdit() {
    editing.value = null;
    form.reset();
    form.from_account_id = props.accounts[0]?.id ?? '';
    form.to_account_id = props.accounts[1]?.id ?? '';
    fromBank.value = accountBankKey(props.accounts[0] ?? {});
    toBank.value = accountBankKey(props.accounts[1] ?? props.accounts[0] ?? {});
    form.date = today;
    form.clearErrors();
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
                <div class="grid grid-cols-1 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-2">
                    <div>
                        <InputLabel value="Banco" />
                        <SelectInput v-model="filters.institution_id" class="mt-1 block w-full" @change="applyFilters">
                            <option value="">Todos os bancos</option>
                            <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                        </SelectInput>
                    </div>

                    <div>
                        <InputLabel value="Conta" />
                        <SelectInput
                            v-model="filters.account_id"
                            class="mt-1 block w-full disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
                            :disabled="!filters.institution_id"
                            @change="applyFilters"
                        >
                            <option value="">
                                {{ filters.institution_id ? 'Todas as contas' : 'Selecione um banco' }}
                            </option>
                            <option v-for="a in accountsForFilterBank" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div class="overflow-hidden bg-white p-5 shadow sm:rounded-lg">
                    <h3 class="font-medium text-gray-900">{{ editing ? 'Editar transferência' : 'Nova transferência' }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Use para mover dinheiro entre suas contas: aporte/resgate de investimento, pagamento entre contas, etc.
                    </p>
                    <form class="mt-4 space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel value="De" />
                            <div class="mt-1 grid grid-cols-2 gap-4">
                                <SelectInput v-model="fromBank" class="block w-full">
                                    <option v-for="b in formBanks" :key="b.key" :value="b.key">{{ b.label }}</option>
                                </SelectInput>
                                <div>
                                    <SelectInput id="from_account_id" v-model="form.from_account_id" class="block w-full">
                                        <option v-for="a in accountsForFromBank" :key="a.id" :value="a.id">{{ a.name }}</option>
                                    </SelectInput>
                                    <InputError class="mt-2" :message="form.errors.from_account_id" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Para" />
                            <div class="mt-1 grid grid-cols-2 gap-4">
                                <SelectInput v-model="toBank" class="block w-full">
                                    <option v-for="b in formBanks" :key="b.key" :value="b.key">{{ b.label }}</option>
                                </SelectInput>
                                <div>
                                    <SelectInput id="to_account_id" v-model="form.to_account_id" class="block w-full">
                                        <option v-for="a in accountsForToBank" :key="a.id" :value="a.id">{{ a.name }}</option>
                                    </SelectInput>
                                    <InputError class="mt-2" :message="form.errors.to_account_id" />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="amount" value="Valor" />
                                <TextInput
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.amount" />
                            </div>

                            <div>
                                <InputLabel for="date" value="Data" />
                                <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.date" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="description" value="Descrição (opcional)" />
                            <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div class="flex items-center gap-3">
                            <PrimaryButton :disabled="form.processing">
                                {{ editing ? 'Salvar' : 'Transferir' }}
                            </PrimaryButton>
                            <button
                                v-if="editing"
                                type="button"
                                class="text-sm text-gray-600 hover:text-gray-900"
                                @click="cancelEdit"
                            >
                                Cancelar
                            </button>
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
                                    <button class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(transfer)">
                                        Editar
                                    </button>
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

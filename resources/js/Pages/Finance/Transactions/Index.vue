<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    transactions: Object,
    accounts: Array,
    institutions: Array,
    categories: Array,
    filters: Object,
});

const filters = reactive({
    institution_id: props.filters.institution_id ?? '',
    account_id: props.filters.account_id ?? '',
    category_id: props.filters.category_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const accountsForFilterBank = computed(() =>
    filters.institution_id === ''
        ? props.accounts
        : props.accounts.filter((a) => a.institution?.id === Number(filters.institution_id))
);

function applyFilters() {
    router.get(route('finance.transactions.index'), filters, { preserveState: true, replace: true });
}

watch(
    () => filters.institution_id,
    () => {
        if (!accountsForFilterBank.value.some((a) => a.id === Number(filters.account_id))) {
            filters.account_id = '';
        }
    }
);

function destroyTransaction(transaction) {
    if (confirm('Remover este lançamento?')) {
        useForm({}).delete(route('finance.transactions.destroy', transaction.id), { preserveScroll: true });
    }
}

const showEditModal = ref(false);
const editing = ref(null);

const editForm = useForm({
    account_id: '',
    person_id: '',
    category_id: '',
    type: 'expense',
    description: '',
    is_unknown: false,
    amount: '',
    date: '',
});

function accountBankKey(account) {
    return account.institution?.id ?? 'none';
}

function accountBankLabel(account) {
    return account.institution?.name ?? 'Dinheiro';
}

const editBanks = computed(() => {
    const seen = new Map();

    for (const a of props.accounts) {
        seen.set(accountBankKey(a), accountBankLabel(a));
    }

    return [...seen.entries()]
        .map(([key, label]) => ({ key, label }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const editSelectedBank = ref('');

const editAccountsForBank = computed(() =>
    props.accounts.filter((a) => accountBankKey(a) === editSelectedBank.value)
);

watch(editSelectedBank, () => {
    if (!editAccountsForBank.value.some((a) => a.id === Number(editForm.account_id))) {
        editForm.account_id = editAccountsForBank.value[0]?.id ?? '';
    }
});

watch(
    () => editForm.account_id,
    (accountId) => {
        const account = props.accounts.find((a) => a.id === Number(accountId));
        editForm.person_id = account?.person.id ?? '';
    }
);

function openEdit(transaction) {
    editing.value = transaction;
    editSelectedBank.value = accountBankKey(transaction.account);
    editForm.account_id = transaction.account.id;
    editForm.person_id = transaction.person.id;
    editForm.category_id = transaction.category?.id ?? '';
    editForm.type = transaction.type;
    editForm.description = transaction.description;
    editForm.is_unknown = transaction.is_unknown;
    editForm.amount = transaction.amount;
    editForm.date = transaction.date.slice(0, 10);
    editForm.clearErrors();
    showEditModal.value = true;
}

function submitEdit() {
    editForm.put(route('finance.transactions.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (showEditModal.value = false),
    });
}

function bankLabel(transaction) {
    return transaction.account.institution?.name ?? 'Dinheiro';
}

const groupedByDay = computed(() => {
    const groups = new Map();

    for (const transaction of props.transactions.data) {
        const key = transaction.date.slice(0, 10);

        if (!groups.has(key)) {
            groups.set(key, { date: key, transactions: [] });
        }

        groups.get(key).transactions.push(transaction);
    }

    return [...groups.values()].sort((a, b) => b.date.localeCompare(a.date));
});
</script>

<template>
    <Head title="Transações" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Transações</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="space-y-4 rounded-lg bg-white p-5 shadow">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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

                        <div>
                            <InputLabel value="Categoria" />
                            <SelectInput v-model="filters.category_id" class="mt-1 block w-full" @change="applyFilters">
                                <option value="">Todas as categorias</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </SelectInput>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="De" />
                            <TextInput v-model="filters.from" type="date" class="mt-1 block w-full" @change="applyFilters" />
                        </div>

                        <div>
                            <InputLabel value="Até" />
                            <TextInput v-model="filters.to" type="date" class="mt-1 block w-full" @change="applyFilters" />
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <div v-for="group in groupedByDay" :key="group.date">
                        <div class="bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 sm:px-6">
                            {{ formatDate(group.date) }}
                        </div>
                        <ul class="divide-y divide-gray-200">
                            <li
                                v-for="transaction in group.transactions"
                                :key="transaction.id"
                                class="flex items-center justify-between px-4 py-3 sm:px-6"
                            >
                                <div>
                                    <p class="text-gray-900">
                                        {{ transaction.description }}
                                        <span v-if="transaction.installment_total" class="text-xs text-gray-500">
                                            ({{ transaction.installment_number }}/{{ transaction.installment_total }})
                                        </span>
                                        <span
                                            v-if="transaction.transfer_id"
                                            class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-700"
                                        >
                                            Transferência
                                        </span>
                                        <span
                                            v-if="transaction.is_unknown"
                                            class="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-yellow-700"
                                        >
                                            Desconhecida
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ bankLabel(transaction) }} / {{ transaction.account.name }}
                                        <template v-if="transaction.category"> · {{ transaction.category.name }}</template>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span :class="transaction.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                        {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
                                    </span>
                                    <button
                                        v-if="!transaction.transfer_id"
                                        class="text-sm text-indigo-600 hover:text-indigo-900"
                                        @click="openEdit(transaction)"
                                    >
                                        Editar
                                    </button>
                                    <button class="text-sm text-red-600 hover:text-red-900" @click="destroyTransaction(transaction)">
                                        Remover
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <p v-if="transactions.data.length === 0" class="px-4 py-6 text-sm text-gray-500 sm:px-6">
                        Nenhum lançamento encontrado.
                    </p>

                    <div v-if="transactions.links.length > 3" class="flex flex-wrap gap-2 border-t px-4 py-3 sm:px-6">
                        <Link
                            v-for="link in transactions.links"
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

    <Modal :show="showEditModal" max-width="lg" @close="showEditModal = false">
        <form class="p-6" @submit.prevent="submitEdit">
            <h2 class="text-lg font-medium text-gray-900">Editar lançamento</h2>

            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <div class="flex items-center justify-between">
                        <InputLabel for="edit_description" value="Descrição" />
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <Checkbox v-model:checked="editForm.is_unknown" />
                            Transação desconhecida
                        </label>
                    </div>
                    <TextInput
                        id="edit_description"
                        v-model="editForm.description"
                        class="mt-1 block w-full"
                        :placeholder="editForm.is_unknown ? 'Desconhecido (opcional preencher)' : ''"
                        :required="!editForm.is_unknown"
                    />
                    <InputError class="mt-2" :message="editForm.errors.description" />
                </div>

                <div>
                    <InputLabel for="edit_amount" value="Valor" />
                    <TextInput id="edit_amount" v-model="editForm.amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="editForm.errors.amount" />
                </div>

                <div>
                    <InputLabel for="edit_type" value="Tipo" />
                    <SelectInput id="edit_type" v-model="editForm.type" class="mt-1 block w-full">
                        <option value="income">Receita</option>
                        <option value="expense">Despesa</option>
                    </SelectInput>
                    <InputError class="mt-2" :message="editForm.errors.type" />
                </div>

                <div>
                    <InputLabel for="edit_bank" value="Banco" />
                    <SelectInput id="edit_bank" v-model="editSelectedBank" class="mt-1 block w-full">
                        <option v-for="b in editBanks" :key="b.key" :value="b.key">{{ b.label }}</option>
                    </SelectInput>
                </div>

                <div>
                    <InputLabel for="edit_account_id" value="Conta" />
                    <SelectInput id="edit_account_id" v-model="editForm.account_id" class="mt-1 block w-full">
                        <option v-for="a in editAccountsForBank" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </SelectInput>
                    <InputError class="mt-2" :message="editForm.errors.account_id" />
                    <InputError class="mt-2" :message="editForm.errors.person_id" />
                </div>

                <div>
                    <InputLabel for="edit_date" value="Data" />
                    <TextInput id="edit_date" v-model="editForm.date" type="date" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="editForm.errors.date" />
                </div>

                <div>
                    <InputLabel for="edit_category_id" value="Categoria" />
                    <SelectInput id="edit_category_id" v-model="editForm.category_id" class="mt-1 block w-full">
                        <option value="">Sem categoria</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </SelectInput>
                    <InputError class="mt-2" :message="editForm.errors.category_id" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showEditModal = false">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>

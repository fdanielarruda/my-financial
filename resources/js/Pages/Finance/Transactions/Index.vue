<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    transactions: Object,
    accounts: Array,
    people: Array,
    categories: Array,
    filters: Object,
});

const filters = reactive({
    account_id: props.filters.account_id ?? '',
    person_id: props.filters.person_id ?? '',
    category_id: props.filters.category_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function applyFilters() {
    router.get(route('finance.transactions.index'), filters, { preserveState: true, replace: true });
}

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
    amount: '',
    date: '',
});

function openEdit(transaction) {
    editing.value = transaction;
    editForm.account_id = transaction.account.id;
    editForm.person_id = transaction.person.id;
    editForm.category_id = transaction.category?.id ?? '';
    editForm.type = transaction.type;
    editForm.description = transaction.description;
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
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-5">
                    <SelectInput v-model="filters.account_id" @change="applyFilters">
                        <option value="">Todas as contas</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </SelectInput>

                    <SelectInput v-model="filters.person_id" @change="applyFilters">
                        <option value="">Todas as pessoas</option>
                        <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectInput>

                    <SelectInput v-model="filters.category_id" @change="applyFilters">
                        <option value="">Todas as categorias</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </SelectInput>

                    <TextInput v-model="filters.from" type="date" @change="applyFilters" />
                    <TextInput v-model="filters.to" type="date" @change="applyFilters" />
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
                                    <button class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(transaction)">
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
                    <InputLabel for="edit_description" value="Descrição" />
                    <TextInput id="edit_description" v-model="editForm.description" class="mt-1 block w-full" required />
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
                    <InputLabel for="edit_account_id" value="Conta" />
                    <SelectInput id="edit_account_id" v-model="editForm.account_id" class="mt-1 block w-full">
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </SelectInput>
                    <InputError class="mt-2" :message="editForm.errors.account_id" />
                </div>

                <div>
                    <InputLabel for="edit_person_id" value="Pessoa" />
                    <SelectInput id="edit_person_id" v-model="editForm.person_id" class="mt-1 block w-full">
                        <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectInput>
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

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
import { formatDate, formatMoney, invoiceStatusLabels } from '@/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    invoice: Object,
    prevInvoiceId: Number,
    nextInvoiceId: Number,
    transactions: Array,
    accounts: Array,
    categories: Array,
});

const today = new Date().toISOString().slice(0, 10);

function addMonths(dateString, months) {
    const [year, month, day] = dateString.slice(0, 10).split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1 + months, day));

    return date.toISOString().slice(0, 10);
}

function monthLabel(dateString) {
    return new Date(dateString.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    });
}

/* ---------- Nova compra ---------- */

const form = useForm({
    account_id: props.accounts[0]?.id ?? '',
    person_id: props.accounts[0]?.person.id ?? '',
    category_id: '',
    description: '',
    is_unknown: false,
    amount: '',
    date: today,
    installment_number: 1,
    installment_total: 1,
});

watch(
    () => form.account_id,
    (accountId) => {
        const account = props.accounts.find((a) => a.id === Number(accountId));
        form.person_id = account?.person.id ?? '';
    }
);

const purchasePreview = computed(() => {
    const number = Number(form.installment_number) || 1;
    const total = Number(form.installment_total) || 1;

    if (total <= 1) {
        return `Será lançada 1 compra na fatura de ${monthLabel(props.invoice.reference_month)}.`;
    }

    const firstMonth = addMonths(props.invoice.reference_month, 1 - number);
    const lastMonth = addMonths(props.invoice.reference_month, total - number);

    return `Serão criadas ${total} parcelas de ${formatMoney(form.amount || 0)}, de ${monthLabel(firstMonth)} a ${monthLabel(lastMonth)} `
        + `(esta compra aparece como ${number}/${total} na fatura de ${monthLabel(props.invoice.reference_month)}).`;
});

function submitPurchase() {
    form.transform((data) => ({ ...data, reference_invoice_id: props.invoice.id })).post(
        route('finance.purchases.store', props.invoice.credit_card.id),
        {
            preserveScroll: true,
            onSuccess: () => form.reset('description', 'amount', 'is_unknown', 'installment_number', 'installment_total'),
        }
    );
}

/* ---------- Editar parcela ---------- */

const showEditModal = ref(false);
const editing = ref(null);

const editForm = useForm({
    account_id: '',
    person_id: '',
    description: '',
    is_unknown: false,
    amount: '',
    category_id: '',
    scope: 'this',
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
    editForm.account_id = transaction.account.id;
    editForm.person_id = transaction.person.id;
    editForm.description = transaction.description;
    editForm.is_unknown = transaction.is_unknown;
    editForm.amount = transaction.amount;
    editForm.category_id = transaction.category?.id ?? '';
    editForm.scope = transaction.installment_total ? 'future' : 'this';
    editForm.clearErrors();
    showEditModal.value = true;
}

function submitEdit() {
    editForm.put(route('finance.installments.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (showEditModal.value = false),
    });
}

function toggleReversed(transaction) {
    const scope = transaction.installment_total
        ? prompt('Estornar apenas esta parcela, esta e as futuras, ou todas? (this / future / all)', 'this')
        : 'this';

    if (!scope) return;

    useForm({ scope }).post(route('finance.installments.reverse', transaction.id), { preserveScroll: true });
}

const groupedByDay = computed(() => {
    const groups = new Map();

    for (const transaction of props.transactions) {
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
    <Head :title="`Fatura ${formatDate(invoice.reference_month)}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ invoice.credit_card.institution.name }} / {{ invoice.credit_card.name }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500">
                        Fechamento {{ formatDate(invoice.closing_date) }} · Vencimento {{ formatDate(invoice.due_date) }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('finance.invoices.show', prevInvoiceId)"
                        class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50"
                        title="Fatura anterior"
                    >
                        ←
                    </Link>
                    <span class="min-w-[9rem] text-center text-sm font-semibold capitalize text-gray-700">
                        {{ monthLabel(invoice.reference_month) }}
                    </span>
                    <Link
                        :href="route('finance.invoices.show', nextInvoiceId)"
                        class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50"
                        title="Próxima fatura"
                    >
                        →
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Total da fatura</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ formatMoney(invoice.total) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ invoiceStatusLabels[invoice.status] }}</p>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="font-medium text-gray-900">Nova compra</h3>

                    <form class="mt-4" @submit.prevent="submitPurchase">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <div class="flex items-center justify-between">
                                    <InputLabel for="description" value="Descrição" />
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <Checkbox v-model:checked="form.is_unknown" />
                                        Transação desconhecida
                                    </label>
                                </div>
                                <TextInput
                                    id="description"
                                    v-model="form.description"
                                    class="mt-1 block w-full"
                                    :placeholder="form.is_unknown ? 'Desconhecido (opcional preencher)' : 'Ex: PAG*Estabelecimento'"
                                    :required="!form.is_unknown"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="amount" value="Valor da parcela" />
                                    <TextInput
                                        id="amount"
                                        v-model="form.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="mt-1 block w-full text-lg font-semibold"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.amount" />
                                </div>

                                <div>
                                    <InputLabel for="date" value="Data da compra" />
                                    <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" required />
                                    <InputError class="mt-2" :message="form.errors.date" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="installment_number" value="Parcela neste mês" />
                                    <TextInput
                                        id="installment_number"
                                        v-model="form.installment_number"
                                        type="number"
                                        min="1"
                                        :max="form.installment_total"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.installment_number" />
                                </div>

                                <div>
                                    <InputLabel for="installment_total" value="Total de parcelas" />
                                    <TextInput
                                        id="installment_total"
                                        v-model="form.installment_total"
                                        type="number"
                                        min="1"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.installment_total" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="account_id" value="Conta" />
                                    <SelectInput id="account_id" v-model="form.account_id" class="mt-1 block w-full">
                                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                                            {{ a.name }}
                                        </option>
                                    </SelectInput>
                                    <InputError class="mt-2" :message="form.errors.account_id" />
                                    <InputError class="mt-2" :message="form.errors.person_id" />
                                </div>

                                <div>
                                    <InputLabel for="category_id" value="Categoria (opcional)" />
                                    <SelectInput id="category_id" v-model="form.category_id" class="mt-1 block w-full">
                                        <option value="">Sem categoria</option>
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </SelectInput>
                                </div>
                            </div>

                            <div class="rounded-md bg-blue-50 p-3 text-sm text-blue-800">
                                {{ purchasePreview }}
                            </div>

                            <div>
                                <PrimaryButton :disabled="form.processing">Lançar compra</PrimaryButton>
                            </div>
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
                                v-for="transaction in group.transactions"
                                :key="transaction.id"
                                class="flex items-center justify-between px-4 py-3 sm:px-6"
                            >
                                <div>
                                    <p class="text-gray-900" :class="transaction.reversed ? 'line-through text-gray-400' : ''">
                                        {{ transaction.description }}
                                        <span v-if="transaction.installment_total" class="text-xs text-gray-500">
                                            ({{ transaction.installment_number }}/{{ transaction.installment_total }})
                                        </span>
                                        <span
                                            v-if="transaction.is_unknown"
                                            class="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-yellow-700"
                                        >
                                            Desconhecida
                                        </span>
                                        <span
                                            v-if="transaction.reversed"
                                            class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-green-700"
                                        >
                                            Estornada
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ transaction.account.name }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span :class="transaction.reversed ? 'text-green-600' : 'text-gray-900'">
                                        {{ transaction.reversed ? '-' : '' }}{{ formatMoney(transaction.amount) }}
                                    </span>
                                    <button class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(transaction)">
                                        Editar
                                    </button>
                                    <button class="text-sm text-amber-600 hover:text-amber-900" @click="toggleReversed(transaction)">
                                        {{ transaction.reversed ? 'Desfazer estorno' : 'Estornar' }}
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <p v-if="transactions.length === 0" class="px-4 py-6 text-sm text-gray-500 sm:px-6">
                        Nenhum lançamento nesta fatura.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="showEditModal" max-width="lg" @close="showEditModal = false">
        <form class="p-6" @submit.prevent="submitEdit">
            <h2 class="text-lg font-medium text-gray-900">Editar parcela</h2>

            <div class="mt-6 space-y-4">
                <div>
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
                        :required="!editForm.is_unknown"
                    />
                    <InputError class="mt-2" :message="editForm.errors.description" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="edit_amount" value="Valor" />
                        <TextInput
                            id="edit_amount"
                            v-model="editForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="editForm.errors.amount" />
                    </div>

                    <div>
                        <InputLabel for="edit_category_id" value="Categoria" />
                        <SelectInput id="edit_category_id" v-model="editForm.category_id" class="mt-1 block w-full">
                            <option value="">Sem categoria</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div>
                    <InputLabel for="edit_account_id" value="Conta" />
                    <SelectInput id="edit_account_id" v-model="editForm.account_id" class="mt-1 block w-full">
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                            {{ a.name }}
                        </option>
                    </SelectInput>
                    <InputError class="mt-2" :message="editForm.errors.account_id" />
                </div>

                <div v-if="editing?.installment_total">
                    <InputLabel value="Aplicar em" />
                    <div class="mt-1 grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-medium"
                            :class="
                                editForm.scope === 'this'
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            "
                            @click="editForm.scope = 'this'"
                        >
                            Só esta
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-medium"
                            :class="
                                editForm.scope === 'future'
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-indigo-300 text-indigo-700 hover:bg-indigo-50'
                            "
                            @click="editForm.scope = 'future'"
                        >
                            Esta e as próximas
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-medium"
                            :class="
                                editForm.scope === 'all'
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            "
                            @click="editForm.scope = 'all'"
                        >
                            Todas
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showEditModal = false">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>

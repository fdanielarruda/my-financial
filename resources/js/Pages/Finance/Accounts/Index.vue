<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoney } from '@/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    accounts: Array,
    people: Array,
    institutions: Array,
    accountTypes: Array,
});

function bankLabel(account) {
    return account.institution?.name ?? 'Dinheiro / Sem instituição';
}

function accountValue(account) {
    return account.type === 'credit_card' ? account.open_invoice_total : account.balance;
}

const bankGroups = computed(() => {
    const groups = new Map();

    for (const account of props.accounts) {
        const key = bankLabel(account);

        if (!groups.has(key)) {
            groups.set(key, { bank: key, accounts: [], total: 0 });
        }

        const group = groups.get(key);
        group.accounts.push(account);

        if (account.type !== 'credit_card') {
            group.total += Number(account.balance ?? 0);
        }
    }

    return [...groups.values()].sort((a, b) => a.bank.localeCompare(b.bank));
});

const showModal = ref(false);
const editing = ref(null);

const form = useForm({
    person_id: props.people[0]?.id ?? '',
    institution_id: '',
    name: '',
    type: 'checking',
    initial_balance: 0,
    credit_limit: '',
    closing_day: '',
    due_day: '',
    payment_account_id: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.person_id = props.people[0]?.id ?? '';
    form.clearErrors();
    showModal.value = true;
}

function openEdit(account) {
    editing.value = account;
    form.person_id = account.person.id;
    form.institution_id = account.institution?.id ?? '';
    form.name = account.name;
    form.type = account.type;
    form.initial_balance = account.initial_balance;
    form.credit_limit = account.credit_card?.credit_limit ?? '';
    form.closing_day = account.credit_card?.closing_day ?? '';
    form.due_day = account.credit_card?.due_day ?? '';
    form.payment_account_id = account.credit_card?.payment_account_id ?? '';
    form.clearErrors();
    showModal.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showModal.value = false) };

    if (editing.value) {
        form.put(route('finance.accounts.update', editing.value.id), options);
    } else {
        form.post(route('finance.accounts.store'), options);
    }
}

function destroy(account) {
    if (confirm(`Arquivar a conta "${account.name}"?`)) {
        useForm({}).delete(route('finance.accounts.destroy', account.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Contas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Contas</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <PrimaryButton @click="openCreate">Nova conta</PrimaryButton>
                </div>

                <div v-for="group in bankGroups" :key="group.bank" class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-6">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ group.bank }}</h3>
                        <span class="text-sm font-semibold" :class="group.total < 0 ? 'text-red-600' : 'text-gray-700'">
                            {{ formatMoney(group.total) }}
                        </span>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2 sm:px-6">Conta</th>
                                <th class="px-4 py-2 sm:px-6">Tipo</th>
                                <th class="px-4 py-2 sm:px-6">Pessoa</th>
                                <th class="px-4 py-2 text-right sm:px-6">Saldo / Fatura</th>
                                <th class="px-4 py-2 text-right sm:px-6">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="account in group.accounts" :key="account.id">
                                <td class="px-4 py-3 text-sm sm:px-6">
                                    <Link
                                        :href="route('finance.accounts.show', account.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-600"
                                    >
                                        {{ account.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 sm:px-6">{{ account.type_label }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 sm:px-6">{{ account.person.name }}</td>
                                <td class="px-4 py-3 text-right text-sm sm:px-6">
                                    <span
                                        class="font-medium"
                                        :class="Number(accountValue(account)) < 0 ? 'text-red-600' : 'text-gray-900'"
                                    >
                                        {{ formatMoney(accountValue(account)) }}
                                    </span>
                                    <p v-if="account.type === 'credit_card'" class="text-xs text-gray-500">
                                        Limite disponível: {{ formatMoney(account.available_limit) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right text-sm sm:px-6">
                                    <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(account)">Editar</button>
                                    <button class="ml-3 text-red-600 hover:text-red-900" @click="destroy(account)">Arquivar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="accounts.length === 0" class="rounded-lg bg-white p-5 text-sm text-gray-500 shadow">
                    Nenhuma conta cadastrada ainda.
                </div>
            </div>
        </div>

        <Modal :show="showModal" max-width="lg" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Editar conta' : 'Nova conta' }}
                </h2>

                <div class="mt-6 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="name" value="Nome" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="type" value="Tipo" />
                        <SelectInput id="type" v-model="form.type" class="mt-1 block w-full">
                            <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </SelectInput>
                        <InputError class="mt-2" :message="form.errors.type" />
                    </div>

                    <div>
                        <InputLabel for="person_id" value="Pessoa (dono)" />
                        <SelectInput id="person_id" v-model="form.person_id" class="mt-1 block w-full">
                            <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </SelectInput>
                        <InputError class="mt-2" :message="form.errors.person_id" />
                    </div>

                    <div>
                        <InputLabel for="institution_id" value="Instituição" />
                        <SelectInput id="institution_id" v-model="form.institution_id" class="mt-1 block w-full">
                            <option value="">Nenhuma / Dinheiro</option>
                            <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }}</option>
                        </SelectInput>
                        <InputError class="mt-2" :message="form.errors.institution_id" />
                    </div>

                    <div v-if="form.type !== 'credit_card'">
                        <InputLabel for="initial_balance" value="Saldo inicial" />
                        <TextInput
                            id="initial_balance"
                            v-model="form.initial_balance"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.initial_balance" />
                    </div>

                    <template v-if="form.type === 'credit_card'">
                        <div>
                            <InputLabel for="credit_limit" value="Limite" />
                            <TextInput
                                id="credit_limit"
                                v-model="form.credit_limit"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.credit_limit" />
                        </div>

                        <div>
                            <InputLabel for="payment_account_id" value="Conta pagadora da fatura" />
                            <SelectInput id="payment_account_id" v-model="form.payment_account_id" class="mt-1 block w-full">
                                <option value="">Selecionar depois</option>
                                <option
                                    v-for="a in accounts.filter((a) => a.type !== 'credit_card')"
                                    :key="a.id"
                                    :value="a.id"
                                >
                                    {{ a.name }} ({{ a.person.name }})
                                </option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.payment_account_id" />
                        </div>

                        <div>
                            <InputLabel for="closing_day" value="Dia de fechamento" />
                            <TextInput
                                id="closing_day"
                                v-model="form.closing_day"
                                type="number"
                                min="1"
                                max="31"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.closing_day" />
                        </div>

                        <div>
                            <InputLabel for="due_day" value="Dia de vencimento" />
                            <TextInput
                                id="due_day"
                                v-model="form.due_day"
                                type="number"
                                min="1"
                                max="31"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.due_day" />
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

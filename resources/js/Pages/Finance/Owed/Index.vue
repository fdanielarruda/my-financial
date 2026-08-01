<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    accounts: Array,
    cards: Array,
    people: Array,
    filters: Object,
});

const filters = reactive({
    person_id: props.filters.person_id ?? '',
    month: props.filters.month ?? '',
});

function applyFilters() {
    router.get(route('finance.owed.index'), filters, { preserveState: true, replace: true });
}

function bankLabel(entity) {
    return entity.institution?.name ?? 'Dinheiro';
}

const accountsTotal = computed(() =>
    props.accounts.reduce((sum, a) => sum + Number(a.balance), 0)
);

const cardsTotal = computed(() =>
    props.cards.reduce((sum, c) => sum + Number(c.total), 0)
);
</script>

<template>
    <Head title="Quanto me devem" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Quanto me devem</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-2">
                    <div>
                        <SelectInput v-model="filters.person_id" class="block w-full" @change="applyFilters">
                            <option value="">Todas as pessoas</option>
                            <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </SelectInput>
                    </div>

                    <div>
                        <TextInput v-model="filters.month" type="month" class="block w-full" @change="applyFilters" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Total nas faturas do mês</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatMoney(cardsTotal) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Saldo atual nas contas</p>
                        <p
                            class="mt-1 text-2xl font-semibold"
                            :class="accountsTotal < 0 ? 'text-red-600' : 'text-gray-900'"
                        >
                            {{ formatMoney(accountsTotal) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <h3 class="border-b px-4 py-3 font-medium text-gray-900 sm:px-6">Cartões (fatura do mês)</h3>
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="card in cards"
                            :key="card.id"
                            class="flex items-center justify-between px-4 py-3 sm:px-6"
                        >
                            <div>
                                <Link
                                    v-if="card.invoice_id"
                                    :href="route('finance.invoices.show', card.invoice_id)"
                                    class="font-medium text-gray-900 hover:text-indigo-600"
                                >
                                    {{ bankLabel(card) }} / {{ card.name }}
                                </Link>
                                <p v-else class="font-medium text-gray-900">{{ bankLabel(card) }} / {{ card.name }}</p>
                                <p v-if="card.due_date" class="text-xs text-gray-500">
                                    Vencimento {{ formatDate(card.due_date) }}
                                </p>
                            </div>
                            <span class="font-semibold" :class="Number(card.total) < 0 ? 'text-green-600' : 'text-gray-900'">
                                {{ formatMoney(card.total) }}
                            </span>
                        </li>
                        <li v-if="cards.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhum cartão cadastrado.
                        </li>
                    </ul>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <h3 class="border-b px-4 py-3 font-medium text-gray-900 sm:px-6">Contas (saldo atual)</h3>
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="account in accounts"
                            :key="account.id"
                            class="flex items-center justify-between px-4 py-3 sm:px-6"
                        >
                            <div>
                                <Link
                                    :href="route('finance.accounts.show', account.id)"
                                    class="font-medium text-gray-900 hover:text-indigo-600"
                                >
                                    {{ bankLabel(account) }} / {{ account.name }}
                                </Link>
                                <p class="text-xs text-gray-500">{{ account.person.name }}</p>
                            </div>
                            <span class="font-semibold" :class="Number(account.balance) < 0 ? 'text-red-600' : 'text-gray-900'">
                                {{ formatMoney(account.balance) }}
                            </span>
                        </li>
                        <li v-if="accounts.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhuma conta encontrada.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

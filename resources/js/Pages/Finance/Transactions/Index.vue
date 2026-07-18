<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

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
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="transaction in transactions.data"
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
                                    {{ formatDate(transaction.date) }} · {{ transaction.account.name }} · {{ transaction.person.name }}
                                    <template v-if="transaction.category"> · {{ transaction.category.name }}</template>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span :class="transaction.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                    {{ transaction.type === 'income' ? '+' : '-' }}{{ formatMoney(transaction.amount) }}
                                </span>
                                <button class="text-sm text-red-600 hover:text-red-900" @click="destroyTransaction(transaction)">
                                    Remover
                                </button>
                            </div>
                        </li>
                        <li v-if="transactions.data.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhum lançamento encontrado.
                        </li>
                    </ul>

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
</template>

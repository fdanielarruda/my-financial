<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoney } from '@/finance';
import { Link, Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    totals: Object,
    groups: Array,
    accounts: Array,
    people: Array,
    filters: Object,
});

const filters = reactive({
    account_id: props.filters.account_id ?? '',
    person_id: props.filters.person_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function applyFilters() {
    router.get(route('finance.summary.index'), filters, { preserveState: true, replace: true });
}

function initials(name) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
</script>

<template>
    <Head title="Resumo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Resumo</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-4">
                    <SelectInput v-model="filters.account_id" @change="applyFilters">
                        <option value="">Todas as contas</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </SelectInput>

                    <SelectInput v-model="filters.person_id" @change="applyFilters">
                        <option value="">Todas as pessoas</option>
                        <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectInput>

                    <TextInput v-model="filters.from" type="date" @change="applyFilters" />
                    <TextInput v-model="filters.to" type="date" @change="applyFilters" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Receitas no período</p>
                        <p class="mt-1 text-2xl font-semibold text-green-600">{{ formatMoney(totals.income) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Despesas no período</p>
                        <p class="mt-1 text-2xl font-semibold text-red-600">{{ formatMoney(totals.expense) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Saldo do período</p>
                        <p
                            class="mt-1 text-2xl font-semibold"
                            :class="Number(totals.income) - Number(totals.expense) < 0 ? 'text-red-600' : 'text-gray-900'"
                        >
                            {{ formatMoney(Number(totals.income) - Number(totals.expense)) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="group in groups"
                            :key="group.account.id"
                            class="flex items-center justify-between px-4 py-4 sm:px-6"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700"
                                >
                                    {{ initials(group.account.person.name) }}
                                </span>
                                <div>
                                    <Link
                                        :href="route('finance.accounts.show', group.account.id)"
                                        class="font-medium text-gray-900 hover:text-indigo-600"
                                    >
                                        {{ group.account.name }} · {{ group.account.person.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500">{{ group.count }} lançamento(s) no período</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p
                                    class="font-semibold"
                                    :class="Number(group.balance) < 0 ? 'text-red-600' : 'text-gray-900'"
                                >
                                    {{ formatMoney(group.balance) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="text-green-600">+{{ formatMoney(group.income) }}</span>
                                    ·
                                    <span class="text-red-600">-{{ formatMoney(group.expense) }}</span>
                                </p>
                            </div>
                        </li>
                        <li v-if="groups.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhum lançamento no período selecionado.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

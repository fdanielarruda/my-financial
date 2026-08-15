<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    totals: Object,
    monthly: Array,
    transactionsByMonth: Object,
    categoryBreakdown: Array,
    accountsSummary: Array,
    people: Array,
    categories: Array,
    filters: Object,
});

const filters = reactive({
    person_id: props.filters.person_id ?? '',
    category_id: props.filters.category_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    view: props.filters.view ?? 'overview',
});

const views = [
    { key: 'overview', label: 'Visão geral' },
    { key: 'credit_card', label: 'Cartão' },
    { key: 'investments', label: 'Investimentos' },
];

function applyFilters() {
    router.get(route('finance.reports.index'), filters, { preserveState: true, replace: true });
}

function setView(view) {
    filters.view = view;
    applyFilters();
}

const balance = computed(() => Number(props.totals.income) - Number(props.totals.expense));

function monthLabel(month) {
    const [year, m] = month.split('-');
    return new Date(Number(year), Number(m) - 1, 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
}

const maxMonthlyValue = computed(() =>
    Math.max(1, ...props.monthly.flatMap((m) => [Number(m.income), Number(m.expense)]))
);

const maxCategoryValue = computed(() => Math.max(1, ...props.categoryBreakdown.map((c) => Number(c.amount))));

const categoryColors = ['#6366f1', '#ef4444', '#10b981', '#f59e0b', '#3b82f6', '#ec4899', '#14b8a6', '#a855f7', '#84cc16', '#f97316'];

function categoryColor(row, index) {
    return row.category?.color || categoryColors[index % categoryColors.length];
}

/* ---------- Transações do mês ---------- */

const showMonthModal = ref(false);
const selectedMonth = ref(null);

const selectedMonthTransactions = computed(() => props.transactionsByMonth[selectedMonth.value] ?? []);

function openMonth(month) {
    selectedMonth.value = month;
    showMonthModal.value = true;
}
</script>

<template>
    <Head title="Relatórios" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Relatórios</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-2 rounded-lg bg-white p-2 shadow">
                    <button
                        v-for="v in views"
                        :key="v.key"
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium"
                        :class="
                            filters.view === v.key
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        "
                        @click="setView(v.key)"
                    >
                        {{ v.label }}
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 rounded-lg bg-white p-5 shadow sm:grid-cols-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500">Pessoa</label>
                        <SelectInput v-model="filters.person_id" class="mt-1 block w-full" @change="applyFilters">
                            <option value="">Todas as pessoas</option>
                            <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </SelectInput>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-500">Categoria</label>
                        <SelectInput v-model="filters.category_id" class="mt-1 block w-full" @change="applyFilters">
                            <option value="">Todas as categorias</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </SelectInput>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-500">De</label>
                        <TextInput v-model="filters.from" type="date" class="mt-1 block w-full" @change="applyFilters" />
                    </div>

                    <div>
                        <label class="text-xs font-medium text-gray-500">Até</label>
                        <TextInput v-model="filters.to" type="date" class="mt-1 block w-full" @change="applyFilters" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Entradas no período</p>
                        <p class="mt-1 text-2xl font-semibold text-green-600">{{ formatMoney(totals.income) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Saídas no período</p>
                        <p class="mt-1 text-2xl font-semibold text-red-600">{{ formatMoney(totals.expense) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Saldo do período</p>
                        <p class="mt-1 text-2xl font-semibold" :class="balance < 0 ? 'text-red-600' : 'text-gray-900'">
                            {{ formatMoney(balance) }}
                        </p>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="font-medium text-gray-900">Entradas x Saídas por mês</h3>

                    <div v-if="monthly.length > 0" class="mt-6 flex items-end gap-4 overflow-x-auto pb-2">
                        <div v-for="m in monthly" :key="m.month" class="flex min-w-[3.5rem] flex-col items-center gap-1">
                            <div class="flex h-40 items-end gap-1">
                                <div
                                    class="w-4 rounded-t bg-green-500"
                                    :style="{ height: `${(Number(m.income) / maxMonthlyValue) * 100}%` }"
                                    :title="`Entradas: ${formatMoney(m.income)}`"
                                ></div>
                                <div
                                    class="w-4 rounded-t bg-red-500"
                                    :style="{ height: `${(Number(m.expense) / maxMonthlyValue) * 100}%` }"
                                    :title="`Saídas: ${formatMoney(m.expense)}`"
                                ></div>
                            </div>
                            <button
                                type="button"
                                class="text-xs capitalize text-indigo-600 underline-offset-2 hover:underline"
                                @click="openMonth(m.month)"
                            >
                                {{ monthLabel(m.month) }}
                            </button>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">Nenhum lançamento no período selecionado.</p>

                    <div class="mt-4 flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-green-500"></span> Entradas</span>
                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span> Saídas</span>
                    </div>

                    <div v-if="monthly.length > 0" class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="py-2 pr-4">Mês</th>
                                    <th class="py-2 pr-4 text-right">Entradas</th>
                                    <th class="py-2 pr-4 text-right">Saídas</th>
                                    <th class="py-2 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="m in monthly" :key="m.month">
                                    <td class="py-2 pr-4">
                                        <button
                                            type="button"
                                            class="capitalize text-indigo-600 hover:underline"
                                            @click="openMonth(m.month)"
                                        >
                                            {{ monthLabel(m.month) }}
                                        </button>
                                    </td>
                                    <td class="py-2 pr-4 text-right text-green-600">{{ formatMoney(m.income) }}</td>
                                    <td class="py-2 pr-4 text-right text-red-600">{{ formatMoney(m.expense) }}</td>
                                    <td
                                        class="py-2 text-right font-medium"
                                        :class="Number(m.income) - Number(m.expense) < 0 ? 'text-red-600' : 'text-gray-900'"
                                    >
                                        {{ formatMoney(Number(m.income) - Number(m.expense)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="font-medium text-gray-900">Gastos por categoria</h3>

                    <div v-if="categoryBreakdown.length > 0" class="mt-4 space-y-3">
                        <div v-for="(row, index) in categoryBreakdown" :key="row.category?.id ?? 'none'">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-700">{{ row.category?.name ?? 'Sem categoria' }}</span>
                                <span class="font-medium text-gray-900">{{ formatMoney(row.amount) }}</span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100">
                                <div
                                    class="h-2 rounded-full"
                                    :style="{
                                        width: `${(Number(row.amount) / maxCategoryValue) * 100}%`,
                                        backgroundColor: categoryColor(row, index),
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">Nenhuma despesa no período selecionado.</p>
                </div>

                <div v-if="filters.view !== 'overview'" class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <div class="border-b px-4 py-3 sm:px-6">
                        <h3 class="font-medium text-gray-900">
                            {{ filters.view === 'credit_card' ? 'Cartões de crédito' : 'Contas de investimento' }}
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="group in accountsSummary"
                            :key="group.account.id"
                            class="flex items-center justify-between px-4 py-4 sm:px-6"
                        >
                            <div>
                                <Link
                                    :href="route('finance.accounts.show', group.account.id)"
                                    class="font-medium text-gray-900 hover:text-indigo-600"
                                >
                                    {{ group.account.name }}
                                </Link>
                                <p class="text-xs text-gray-500">{{ group.account.institution?.name ?? 'Dinheiro' }}</p>
                            </div>
                            <div class="text-right">
                                <p v-if="group.balance !== null" class="font-semibold text-gray-900">
                                    {{ formatMoney(group.balance) }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="text-green-600">+{{ formatMoney(group.income) }}</span>
                                    ·
                                    <span class="text-red-600">-{{ formatMoney(group.expense) }}</span>
                                </p>
                            </div>
                        </li>
                        <li v-if="accountsSummary.length === 0" class="px-4 py-6 text-sm text-gray-500 sm:px-6">
                            Nenhuma conta com lançamentos no período selecionado.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="showMonthModal" max-width="lg" @close="showMonthModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium capitalize text-gray-900">
                Lançamentos de {{ selectedMonth ? monthLabel(selectedMonth) : '' }}
            </h2>

            <ul class="mt-4 max-h-96 divide-y divide-gray-100 overflow-y-auto">
                <li v-for="t in selectedMonthTransactions" :key="t.id" class="flex items-center justify-between py-2">
                    <div>
                        <p class="text-sm text-gray-900">{{ t.description }}</p>
                        <p class="text-xs text-gray-500">
                            {{ formatDate(t.date) }} · {{ t.account }}
                            <template v-if="t.category"> · {{ t.category }}</template>
                        </p>
                    </div>
                    <span class="text-sm font-medium" :class="t.type === 'income' ? 'text-green-600' : 'text-red-600'">
                        {{ t.type === 'income' ? '+' : '-' }}{{ formatMoney(t.amount) }}
                    </span>
                </li>
                <li v-if="selectedMonthTransactions.length === 0" class="py-6 text-center text-sm text-gray-500">
                    Nenhum lançamento neste mês.
                </li>
            </ul>
        </div>
    </Modal>
</template>

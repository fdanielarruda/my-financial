<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate, formatMoney } from '@/finance';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    balancesByPerson: Object,
    netWorth: String,
    openInvoices: Array,
    upcomingRecurring: Array,
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow">
                    <p class="text-sm text-gray-500">Saldo total (contas de débito/investimento)</p>
                    <p
                        class="text-3xl font-semibold"
                        :class="Number(netWorth) < 0 ? 'text-red-600' : 'text-gray-900'"
                    >
                        {{ formatMoney(netWorth) }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(balance, person) in balancesByPerson"
                        :key="person"
                        class="rounded-lg bg-white p-5 shadow"
                    >
                        <p class="text-sm text-gray-500">{{ person }}</p>
                        <p class="text-xl font-semibold" :class="Number(balance) < 0 ? 'text-red-600' : 'text-gray-900'">
                            {{ formatMoney(balance) }}
                        </p>
                    </div>
                </div>

                <div v-if="openInvoices.length > 0" class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <h3 class="border-b px-4 py-3 font-medium text-gray-900 sm:px-6">Faturas abertas</h3>
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="invoice in openInvoices"
                            :key="invoice.account.id"
                            class="flex items-center justify-between px-4 py-3 sm:px-6"
                        >
                            <Link
                                :href="route('finance.accounts.show', invoice.account.id)"
                                class="text-indigo-600 hover:text-indigo-900"
                            >
                                {{ invoice.account.name }}
                            </Link>
                            <span class="font-medium text-gray-900">{{ formatMoney(invoice.total) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <h3 class="border-b px-4 py-3 font-medium text-gray-900 sm:px-6">Próximas recorrências (14 dias)</h3>
                    <ul class="divide-y divide-gray-200">
                        <li
                            v-for="recurring in upcomingRecurring"
                            :key="recurring.id"
                            class="flex items-center justify-between px-4 py-3 sm:px-6"
                        >
                            <div>
                                <p class="text-gray-900">{{ recurring.description }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ formatDate(recurring.next_run_date) }} · {{ recurring.account.name }} · {{ recurring.person.name }}
                                </p>
                            </div>
                            <span :class="recurring.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                {{ recurring.type === 'income' ? '+' : '-' }}{{ formatMoney(recurring.amount) }}
                            </span>
                        </li>
                        <li v-if="upcomingRecurring.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nada previsto para os próximos 14 dias.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

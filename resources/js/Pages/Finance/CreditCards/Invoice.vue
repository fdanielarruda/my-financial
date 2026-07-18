<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatMoney, invoiceStatusLabels } from '@/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    invoice: Object,
    transactions: Array,
    accounts: Array,
});

const form = useForm({
    payment_account_id: props.invoice.credit_card.payment_account_id ?? '',
    date: new Date().toISOString().slice(0, 10),
});

function pay() {
    if (!confirm('Confirmar pagamento desta fatura?')) return;

    form.post(route('finance.invoices.pay', props.invoice.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Fatura ${formatDate(invoice.reference_month)}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Fatura {{ invoice.credit_card.account.name }} — {{ formatDate(invoice.reference_month) }}
                </h2>
                <Link :href="route('finance.accounts.show', invoice.credit_card.account_id)" class="text-sm text-indigo-600 hover:text-indigo-900">
                    Voltar para a conta
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Total</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ formatMoney(invoice.total) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Vencimento</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ formatDate(invoice.due_date) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow">
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ invoiceStatusLabels[invoice.status] }}</p>
                    </div>
                </div>

                <div v-if="invoice.status !== 'paid'" class="overflow-hidden bg-white p-5 shadow sm:rounded-lg">
                    <h3 class="font-medium text-gray-900">Pagar fatura</h3>
                    <form class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3" @submit.prevent="pay">
                        <div>
                            <InputLabel for="payment_account_id" value="Pagar com" />
                            <SelectInput id="payment_account_id" v-model="form.payment_account_id" class="mt-1 block w-full">
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                            <InputError class="mt-2" :message="form.errors.payment_account_id" />
                        </div>

                        <div>
                            <InputLabel for="date" value="Data do pagamento" />
                            <TextInput id="date" v-model="form.date" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.date" />
                        </div>

                        <div class="flex items-end">
                            <PrimaryButton :disabled="form.processing">Pagar fatura</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                    <h3 class="border-b px-4 py-3 font-medium text-gray-900 sm:px-6">Lançamentos</h3>
                    <ul class="divide-y divide-gray-200">
                        <li v-for="transaction in transactions" :key="transaction.id" class="flex items-center justify-between px-4 py-3 sm:px-6">
                            <div>
                                <p class="text-gray-900">
                                    {{ transaction.description }}
                                    <span v-if="transaction.installment_total" class="text-xs text-gray-500">
                                        ({{ transaction.installment_number }}/{{ transaction.installment_total }})
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ formatDate(transaction.date) }} · {{ transaction.person.name }}
                                    <template v-if="transaction.category"> · {{ transaction.category.name }}</template>
                                </p>
                            </div>
                            <span class="text-gray-900">{{ formatMoney(transaction.amount) }}</span>
                        </li>
                        <li v-if="transactions.length === 0" class="px-4 py-6 text-sm text-gray-500">
                            Nenhum lançamento nesta fatura.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

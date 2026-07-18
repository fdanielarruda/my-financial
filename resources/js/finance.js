export function formatMoney(value) {
    const number = Number(value ?? 0);

    return number.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function formatDate(value) {
    if (!value) return '';

    return new Date(value + 'T00:00:00').toLocaleDateString('pt-BR');
}

export const accountTypeLabels = {
    checking: 'Conta corrente',
    savings: 'Poupança',
    wallet: 'Carteira/Dinheiro',
    investment: 'Investimento',
    credit_card: 'Cartão de crédito',
};

export const frequencyLabels = {
    weekly: 'Semanal',
    monthly: 'Mensal',
    yearly: 'Anual',
};

export const invoiceStatusLabels = {
    open: 'Aberta',
    closed: 'Fechada',
    paid: 'Paga',
};

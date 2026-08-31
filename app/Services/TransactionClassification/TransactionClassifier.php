<?php

namespace App\Services\TransactionClassification;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

/**
 * Asks OpenAI to suggest a category for a batch of transactions, based on
 * their description. Suggestions are only ever a starting point for the
 * user to review and confirm before saving.
 */
class TransactionClassifier
{
    /**
     * @return array<int, array{transaction_id: int, category_id: int|null}>
     */
    public function classify(Collection $transactions, Collection $categories): array
    {
        if ($transactions->isEmpty()) {
            return [];
        }

        if (! config('openai.api_key')) {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        try {
            $response = OpenAI::responses()->create([
                'model' => config('services.openai.model'),
                'instructions' => $this->systemPrompt(),
                'input' => [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => $this->userPrompt($transactions, $categories),
                    ]],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'transaction_categories',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);
        } catch (ErrorException|RateLimitException|TransporterException $exception) {
            throw new RuntimeException('Não foi possível classificar as transações com a OpenAI API: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->status !== 'completed' || ! $response->outputText) {
            throw new RuntimeException('A OpenAI não conseguiu classificar as transações (a resposta ficou incompleta ou foi recusada).');
        }

        $payload = json_decode($response->outputText, associative: true);

        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            throw new RuntimeException('A resposta da OpenAI não veio no formato esperado.');
        }

        $categoriesByName = $categories->keyBy(fn ($category) => mb_strtolower($category->name));
        $transactionIds = $transactions->pluck('id')->flip();

        return collect($payload['items'])
            ->filter(fn (array $item) => $transactionIds->has($item['transaction_id'] ?? null))
            ->map(function (array $item) use ($categoriesByName) {
                $categoryName = $item['category_name'] ?? null;
                $category = $categoryName ? $categoriesByName->get(mb_strtolower($categoryName)) : null;

                return [
                    'transaction_id' => (int) $item['transaction_id'],
                    'category_id' => $category?->id,
                ];
            })
            ->values()
            ->all();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um assistente que sugere categorias para lançamentos financeiros
            (receitas e despesas) em um sistema de controle financeiro pessoal
            brasileiro. Baseie-se na descrição do lançamento e no tipo (receita ou
            despesa). Se não houver correspondência clara, não sugira categoria
            nenhuma para aquele item.
            PROMPT;
    }

    private function userPrompt(Collection $transactions, Collection $categories): string
    {
        $categoryList = $categories->isEmpty()
            ? 'nenhuma categoria cadastrada'
            : $categories->map(fn ($c) => "\"{$c->name}\" ({$c->type->label()})")->implode(', ');

        $transactionList = $transactions->map(function (Transaction $transaction) {
            return sprintf(
                '- id=%d | descrição="%s" | tipo=%s | valor=%s | data=%s',
                $transaction->id,
                $transaction->description,
                $transaction->type->value,
                $transaction->amount,
                $transaction->date->toDateString(),
            );
        })->implode("\n");

        return <<<PROMPT
            Sugira uma categoria para cada lançamento listado abaixo, quando fizer
            sentido claro pela descrição (ex.: nome de estabelecimento, tipo de gasto
            ou receita).

            Lançamentos:
            {$transactionList}

            Categorias disponíveis: {$categoryList}.

            Para cada lançamento, retorne "transaction_id" (o id informado acima) e
            "category_name", que deve ser exatamente um dos nomes da lista acima, ou
            null quando não houver correspondência clara.
            PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'transaction_id' => ['type' => 'integer'],
                            'category_name' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['transaction_id', 'category_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
    }
}

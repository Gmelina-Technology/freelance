<?php

namespace App\Services;

use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Actions\Sales\SaveSaleConversionAction;
use App\Models\Account;
use App\Models\Sale;
use App\Models\SaleConversion;
use App\Models\User;
use App\Services\Sales\SaleConversionBatchItem;
use App\Services\Sales\SaleConversionCalculator;
use App\Services\Sales\SaleConversionValidator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SaleConversionService
{
    public const MAX_BATCH_SIZE = 100;

    public function __construct(
        private SaleConversionValidator $validator,
        private SaleConversionCalculator $calculator,
    ) {}

    /**
     * Record or correct a sale's conversion into its account's currency.
     *
     * @param  array<string, mixed>  $data  raw form input; only allow-listed keys survive validation
     *
     * @throws SaleConversionException
     * @throws ValidationException
     */
    public function convert(Sale $sale, array $data, User $convertedBy): SaleConversion
    {
        $accountCurrencyCode = $this->guardAccountCurrency($sale);

        $input = $this->validator->validate($sale, $data);

        return SaveSaleConversionAction::handle(
            $sale,
            $accountCurrencyCode,
            $this->calculator->fromInput($sale, $input),
            $convertedBy,
            $input['currency_code'] ?? null,
        );
    }

    /**
     * Convert every given sale at one shared rate, or none of them.
     *
     * @param  EloquentCollection<int, Sale>  $sales
     * @param  array<string, mixed>  $data  raw batch form input; only allow-listed keys survive validation
     * @return Collection<int, SaleConversion>
     *
     * @throws SaleConversionException
     * @throws ValidationException
     */
    public function convertBatch(Account $account, EloquentCollection $sales, array $data, User $convertedBy): Collection
    {
        $accountCurrencyCode = $this->guardBatch($account, $sales);

        $this->guardBatchEligibility($sales, $accountCurrencyCode);

        $batch = $this->validator->validateBatch($data);

        return SaveSaleConversionAction::handleMany($this->batchItems($sales, $batch), $accountCurrencyCode, $convertedBy);
    }

    /**
     * Checks that need no sale data beyond ids; returns the account's currency code.
     *
     * @param  EloquentCollection<int, Sale>  $sales
     *
     * @throws SaleConversionException
     */
    private function guardBatch(Account $account, EloquentCollection $sales): string
    {
        $accountCurrencyCode = $account->currency_code;

        if ($accountCurrencyCode === null) {
            throw SaleConversionException::missingAccountCurrency();
        }

        if ($sales->isEmpty()) {
            throw SaleConversionException::emptyBatch();
        }

        if ($sales->count() > self::MAX_BATCH_SIZE) {
            throw SaleConversionException::batchTooLarge(self::MAX_BATCH_SIZE);
        }

        if ($sales->contains(fn (Sale $sale): bool => $sale->account_id !== $account->getKey())) {
            throw SaleConversionException::batchHasForeignSales();
        }

        return $accountCurrencyCode;
    }

    /**
     * @param  EloquentCollection<int, Sale>  $sales
     *
     * @throws SaleConversionException
     */
    private function guardBatchEligibility(EloquentCollection $sales, string $accountCurrencyCode): void
    {
        $sales->loadMissing('saleConversion');

        if (($count = $sales->filter(fn (Sale $sale): bool => $sale->isConverted())->count()) > 0) {
            throw SaleConversionException::batchHasConvertedSales($count);
        }

        if (($count = $sales->whereNull('currency_code')->count()) > 0) {
            throw SaleConversionException::batchHasUnknownCurrency($count);
        }

        if (($count = $sales->where('currency_code', $accountCurrencyCode)->count()) > 0) {
            throw SaleConversionException::batchHasAccountCurrencySales($count, $accountCurrencyCode);
        }

        $currencyCodes = $sales->pluck('currency_code')->unique()->values();

        if ($currencyCodes->count() > 1) {
            throw SaleConversionException::mixedCurrencies($currencyCodes->all());
        }
    }

    /**
     * @param  EloquentCollection<int, Sale>  $sales
     * @param  array<string, mixed>  $batch  validated batch input
     * @return list<SaleConversionBatchItem>
     *
     * @throws SaleConversionException
     */
    private function batchItems(EloquentCollection $sales, array $batch): array
    {
        return $sales->map(fn (Sale $sale): SaleConversionBatchItem => $this->batchItem($sale, $batch))->values()->all();
    }

    /**
     * @param  array{exchange_rate: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string}  $batch
     *
     * @throws SaleConversionException
     */
    private function batchItem(Sale $sale, array $batch): SaleConversionBatchItem
    {
        $input = $this->calculator->inputAtRate($sale, $batch);

        try {
            $valid = $this->validator->validate($sale, $input->toArray());
        } catch (ValidationException $exception) {
            throw SaleConversionException::invalidSaleInBatch($sale, collect($exception->errors())->flatten()->first());
        }

        return new SaleConversionBatchItem($sale, $this->calculator->fromInput($sale, $valid));
    }

    /**
     * @throws SaleConversionException
     */
    private function guardAccountCurrency(Sale $sale): string
    {
        $accountCurrencyCode = $sale->account?->currency_code;

        if ($accountCurrencyCode === null) {
            throw SaleConversionException::missingAccountCurrency();
        }

        $convertedCurrencyCode = $sale->saleConversion?->converted_currency_code;

        if ($convertedCurrencyCode !== null && $convertedCurrencyCode !== $accountCurrencyCode) {
            throw SaleConversionException::accountCurrencyChanged($convertedCurrencyCode, $accountCurrencyCode);
        }

        return $accountCurrencyCode;
    }
}

<?php

namespace App\Services\Sales;

use App\Exceptions\SaleConversionException;
use App\Models\Sale;
use App\Models\SaleConversion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Validates conversion input. Only the keys with rules below survive, so nothing else in the
 * input can reach the conversion.
 */
class SaleConversionValidator
{
    public function __construct(private SaleConversionCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{converted_amount: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string, currency_code?: string}
     *
     * @throws ValidationException
     */
    public function validate(Sale $sale, array $data): array
    {
        $max = 'max:'.SaleConversion::MAX_AMOUNT;

        $validator = Validator::make($data, [
            'converted_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', $max],
            'fee_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', $max],
            'tax_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', $max],
            'conversion_note' => ['nullable', 'string', 'max:1000'],
            'currency_code' => $sale->currency_code === null
                ? ['required', 'string', 'exists:currencies,code']
                : [Rule::excludeIf(true)],
        ], [
            'currency_code.required' => SaleConversionException::unknownOriginalCurrency()->getMessage(),
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($data) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->calculator->grossCents($data) > $this->calculator->grossCents(['converted_amount' => SaleConversion::MAX_AMOUNT])) {
                $validator->errors()->add(
                    'converted_amount',
                    'Net, fees and taxes together can be at most '.number_format((float) SaleConversion::MAX_AMOUNT, 2).'.',
                );
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{exchange_rate: float|int|string, fee_amount?: float|int|string|null, tax_amount?: float|int|string|null, conversion_note?: ?string}
     *
     * @throws ValidationException
     */
    public function validateBatch(array $data): array
    {
        $max = 'max:'.SaleConversion::MAX_AMOUNT;

        return Validator::make($data, [
            'exchange_rate' => ['required', 'numeric', 'decimal:0,10', 'gt:0', $max],
            'fee_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', $max],
            'tax_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', $max],
            'conversion_note' => ['nullable', 'string', 'max:1000'],
        ])->validate();
    }
}

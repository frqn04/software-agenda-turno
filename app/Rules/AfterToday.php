<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class AfterToday implements ValidationRule
{
    private int $hoursAfter;

    public function __construct(int $hoursAfter = 2)
    {
        $this->hoursAfter = $hoursAfter;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = Carbon::parse($value);
        $minimumDate = now()->addHours($this->hoursAfter);

        if ($date->isBefore($minimumDate)) {
            $fail("La fecha debe ser al menos {$this->hoursAfter} horas después del momento actual.");
        }
    }
}

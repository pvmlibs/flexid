<?php

declare(strict_types=1);

namespace Tests\Internal;

trait HasCharDistributionTesting
{
    /**
     * @param \SplFixedArray<string> $ids
     *
     * @return array{real: float, random: float, mostFrequentChar: string, mean: float} max deviation from mean
     */
    private function getMaxDeviation(\SplFixedArray $ids, string $alphabet): array
    {
        $alphabetLength = strlen($alphabet);
        $charsOccurrence = [];
        $charsOccurrenceRandom = [];
        for ($i = 0; $i < $alphabetLength; $i++) {
            $charsOccurrence[$alphabet[$i]] = 0;
            $charsOccurrenceRandom[$alphabet[$i]] = 0;
        }

        $charsTotalSum = 0;
        for ($i = 0; $i < $ids->count(); $i++) {
            /** @var string $id */
            $id = $ids[$i];
            for ($j = 0; $j < strlen($id); $j++) {
                $charsOccurrence[$id[$j]]++;
                $charsOccurrenceRandom[$alphabet[random_int(0, $alphabetLength - 1)]]++;
            }
            $charsTotalSum += strlen($id);
        }
        $mean = (float) $charsTotalSum / (float) $alphabetLength;

        $min = $minRand = PHP_INT_MAX;
        $max = $maxRand = 0;
        $mostFrequentChar = '';

        foreach ($charsOccurrence as $char => $value) {
            if ($value < $min) {
                $min = $value;
            }
            if ($value > $max) {
                $mostFrequentChar = $char;
                $max = $value;
            }
        }
        foreach ($charsOccurrenceRandom as $value) {
            if ($value < $minRand) {
                $minRand = $value;
            }
            if ($value > $maxRand) {
                $maxRand = $value;
            }
        }

        return [
            'real' => max($mean - $min, $max - $mean) / $mean,
            'random' => max($mean - $minRand, $maxRand - $mean) / $mean,
            'mostFrequentChar' => $mostFrequentChar,
            'mean' => $mean,
        ];
    }
}

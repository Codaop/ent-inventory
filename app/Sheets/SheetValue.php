<?php

namespace App\Sheets;

/**
 * Converts raw Google Sheets cell values (as returned with
 * valueRenderOption=UNFORMATTED_VALUE) into stable PHP types.
 */
final class SheetValue
{
    public static function toBool(mixed $value): bool
    {
        return $value === true || strtoupper(trim((string) $value)) === 'TRUE';
    }

    public static function toNumber(mixed $value): int|float
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $number = $value + 0;

        return floor($number) === $number ? (int) $number : $number;
    }

    /**
     * Normalize a date cell to 'Y-m-d H:i:s'.
     *
     * - Numeric values are Sheets serial dates (days since 1899-12-30,
     *   interpreted as wall-clock dates, matching spreadsheet-local time).
     * - String values are parsed best-effort; unparseable strings pass through.
     * - Empty values become an empty string.
     */
    public static function toDateString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return self::fromSerialDate((float) $value);
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false
            ? (string) $value
            : date('Y-m-d H:i:s', $timestamp);
    }

    private static function fromSerialDate(float $serial): string
    {
        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);

        $date = (new \DateTimeImmutable('1899-12-30'))
            ->modify(sprintf('+%d days', $days))
            ->setTime(0, 0, 0)
            ->modify(sprintf('+%d seconds', $seconds));

        return $date->format('Y-m-d H:i:s');
    }
}

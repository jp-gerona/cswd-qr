<?php

namespace App\Libraries;

use Config\QrBatchSettings;

final class QrBatchPlanner
{
    private static function settings(): QrBatchSettings
    {
        return config('QrBatchSettings');
    }

    public static function cellsPerPage(): int
    {
        return self::settings()->cellsPerPage;
    }

    public static function pagesPerChunk(): int
    {
        return self::settings()->pagesPerChunk;
    }

    public static function maxQuantity(): int
    {
        return self::settings()->maxQuantity;
    }

    // Largest value representable in the configured control-number width
    // (999999 for width 6).
    public static function maxControlNumber(): int
    {
        return (10 ** self::settings()->controlNumberWidth) - 1;
    }

    public static function formatControlNumber(int $sequenceNumber): string
    {
        return str_pad((string) $sequenceNumber, self::settings()->controlNumberWidth, '0', STR_PAD_LEFT);
    }

    public static function controlNumbers(int $quantity, int $startNumber = 1): array
    {
        $formattedControlNumbers = [];
        for ($offset = 0; $offset < $quantity; $offset++) {
            $formattedControlNumbers[] = self::formatControlNumber($startNumber + $offset);
        }

        return $formattedControlNumbers;
    }

    public static function pageCount(int $quantity): int
    {
        return (int) ceil($quantity / self::cellsPerPage());
    }

    public static function chunkCount(int $quantity): int
    {
        return (int) ceil(self::pageCount($quantity) / self::pagesPerChunk());
    }
}

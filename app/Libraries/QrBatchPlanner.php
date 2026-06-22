<?php

namespace App\Libraries;

final class QrBatchPlanner
{
    public const CELLS_PER_PAGE      = 12;
    public const PAGES_PER_CHUNK     = 50;
    public const CONTROL_NUMBER_WIDTH = 6;
    public const MAX_QUANTITY        = 10000;

    // Largest value representable in CONTROL_NUMBER_WIDTH digits (999999 for 6).
    public const MAX_CONTROL_NUMBER  = 999999;

    public static function formatControlNumber(int $sequenceNumber): string
    {
        return str_pad((string) $sequenceNumber, self::CONTROL_NUMBER_WIDTH, '0', STR_PAD_LEFT);
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
        return (int) ceil($quantity / self::CELLS_PER_PAGE);
    }

    public static function chunkCount(int $quantity): int
    {
        return (int) ceil(self::pageCount($quantity) / self::PAGES_PER_CHUNK);
    }
}

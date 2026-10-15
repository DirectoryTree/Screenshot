<?php

namespace DirectoryTree\Screenshot\Enums;

enum PaperSize: string
{
    case A0 = 'a0';
    case A1 = 'a1';
    case A2 = 'a2';
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case A6 = 'a6';
    case Letter = 'letter';
    case Legal = 'legal';
    case Tabloid = 'tabloid';
    case Ledger = 'ledger';

    /**
     * Get the paper width and height in millimeters, before orientation.
     *
     * @return array{float, float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::A0 => [841.0, 1189.0],
            self::A1 => [594.0, 841.0],
            self::A2 => [420.0, 594.0],
            self::A3 => [297.0, 420.0],
            self::A4 => [210.0, 297.0],
            self::A5 => [148.0, 210.0],
            self::A6 => [105.0, 148.0],
            self::Letter => [215.9, 279.4],
            self::Legal => [215.9, 355.6],
            self::Tabloid => [279.4, 431.8],
            self::Ledger => [431.8, 279.4],
        };
    }
}

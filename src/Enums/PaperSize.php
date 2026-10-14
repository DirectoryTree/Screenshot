<?php

namespace DirectoryTree\Screenshot\Enums;

enum PaperSize: string
{
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case Letter = 'letter';
    case Legal = 'legal';

    /**
     * Get the paper width and height in millimeters, before orientation.
     *
     * @return array{float, float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::A3 => [297.0, 420.0],
            self::A4 => [210.0, 297.0],
            self::A5 => [148.0, 210.0],
            self::Letter => [215.9, 279.4],
            self::Legal => [215.9, 355.6],
        };
    }
}

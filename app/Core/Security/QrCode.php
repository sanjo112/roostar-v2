<?php

declare(strict_types=1);

namespace Roostar\Core\Security;

final class QrCode
{
    private const TOTAL_CODEWORDS = [1 => 26, 44, 70, 100, 134, 172];
    private const EC_CODEWORDS = [1 => 7, 10, 15, 20, 26, 18];
    private const ALIGNMENT_POSITIONS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
    ];

    private array $modules = [];
    private array $functionModules = [];
    private int $size = 0;

    public static function svg(string $text, int $scale = 6, int $border = 4): string
    {
        return (new self())->encodeSvg($text, $scale, $border);
    }

    private function encodeSvg(string $text, int $scale, int $border): string
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $version = $this->smallestVersionFor(count($data));
        $this->size = 21 + ($version - 1) * 4;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->functionModules = array_fill(0, $this->size, array_fill(0, $this->size, false));

        $this->drawFunctionPatterns($version);
        $codewords = $this->dataCodewords($data, $version);
        $allCodewords = [...$codewords, ...$this->reedSolomonRemainder($codewords, self::EC_CODEWORDS[$version])];
        $this->drawCodewords($allCodewords);
        $this->applyMask(0);
        $this->drawFormatBits(0);

        return $this->toSvg($scale, $border);
    }

    private function smallestVersionFor(int $byteLength): int
    {
        foreach (range(1, 6) as $version) {
            $dataCodewords = self::TOTAL_CODEWORDS[$version] - self::EC_CODEWORDS[$version];
            $requiredBits = 4 + 8 + ($byteLength * 8);

            if ($requiredBits <= $dataCodewords * 8) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('De QR-code data is te lang.');
    }

    private function dataCodewords(array $bytes, int $version): array
    {
        $capacity = self::TOTAL_CODEWORDS[$version] - self::EC_CODEWORDS[$version];
        $bits = [0, 1, 0, 0];
        $bits = [...$bits, ...$this->intBits(count($bytes), 8)];

        foreach ($bytes as $byte) {
            $bits = [...$bits, ...$this->intBits($byte, 8)];
        }

        $bits = [...$bits, ...array_fill(0, min(4, $capacity * 8 - count($bits)), 0)];

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | $bit;
            }
            $codewords[] = $value;
        }

        for ($pad = 0; count($codewords) < $capacity; $pad++) {
            $codewords[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        return $codewords;
    }

    private function drawFunctionPatterns(int $version): void
    {
        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        foreach (range(0, $this->size - 1) as $i) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        foreach (self::ALIGNMENT_POSITIONS[$version] as $x) {
            foreach (self::ALIGNMENT_POSITIONS[$version] as $y) {
                if ($this->functionModules[$y][$x]) {
                    continue;
                }

                $this->drawAlignmentPattern($x, $y);
            }
        }

        $this->setFunctionModule(8, $this->size - 8, true);
        $this->drawFormatBits(0);
    }

    private function drawFinderPattern(int $centerX, int $centerY): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $centerX + $dx;
                $y = $centerY + $dy;

                if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
                    continue;
                }

                $distance = max(abs($dx), abs($dy));
                $this->setFunctionModule($x, $y, $distance !== 2 && $distance !== 4);
            }
        }
    }

    private function drawAlignmentPattern(int $centerX, int $centerY): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $distance = max(abs($dx), abs($dy));
                $this->setFunctionModule($centerX + $dx, $centerY + $dy, $distance !== 1);
            }
        }
    }

    private function drawCodewords(array $codewords): void
    {
        $bits = [];
        foreach ($codewords as $codeword) {
            $bits = [...$bits, ...$this->intBits($codeword, 8)];
        }

        $bitIndex = 0;
        $direction = -1;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vertical = 0; $vertical < $this->size; $vertical++) {
                $y = $direction === -1 ? $this->size - 1 - $vertical : $vertical;

                for ($column = 0; $column < 2; $column++) {
                    $x = $right - $column;

                    if ($this->functionModules[$y][$x]) {
                        continue;
                    }

                    $this->modules[$y][$x] = ($bits[$bitIndex] ?? 0) === 1;
                    $bitIndex++;
                }
            }

            $direction *= -1;
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->functionModules[$y][$x]) {
                    continue;
                }

                if ($this->maskBit($mask, $x, $y)) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (1 << 3) | $mask; // ECC level L.
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }

        $bits = (($data << 10) | $remainder) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFunctionModule(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFunctionModule(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFunctionModule(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        $this->setFunctionModule(8, $this->size - 8, true);
    }

    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $this->gfMultiply($coefficient, 1);
                $next[$j + 1] ^= $this->gfMultiply($coefficient, $this->gfPow(2, $i));
            }
            $generator = $next;
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= $this->gfMultiply($generator[$i + 1], $factor);
            }
        }

        return $result;
    }

    private function toSvg(int $scale, int $border): string
    {
        $dimension = ($this->size + $border * 2) * $scale;
        $paths = [];

        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $paths[] = 'M' . (($x + $border) * $scale) . ' ' . (($y + $border) * $scale) . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dimension . ' ' . $dimension . '" role="img" aria-label="QR-code voor 2FA">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#111B4E" d="' . implode('', $paths) . '"/>'
            . '</svg>';
    }

    private function setFunctionModule(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->functionModules[$y][$x] = true;
    }

    private function intBits(int $value, int $length): array
    {
        $bits = [];
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }

        return $bits;
    }

    private function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => (($x * $y) % 2 + ($x * $y) % 3) === 0,
            6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0,
            default => ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0,
        };
    }

    private function gfPow(int $x, int $power): int
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = $this->gfMultiply($result, $x);
        }

        return $result;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $result = 0;
        while ($y !== 0) {
            if (($y & 1) !== 0) {
                $result ^= $x;
            }
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
            $y >>= 1;
        }

        return $result & 0xFF;
    }
}

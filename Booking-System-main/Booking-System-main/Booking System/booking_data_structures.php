<?php
declare(strict_types=1);

final class BookingQueue
{
    private array $items = [];

    public function enqueue(array $booking): void
    {
        $this->items[] = $booking;
    }

    public function dequeue(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        return array_shift($this->items);
    }

    public function peek(): ?array
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }
}

final class BookingAlgorithms
{
    private static function compareByField(array $left, array $right, string $field): int
    {
        if ($field === 'pool_size') {
            $poolOrder = static fn ($value): int => $value === 'Small Pool' ? 0 : 1;
            return $poolOrder($left[$field]) <=> $poolOrder($right[$field]);
        }
        if ($field === 'BookingID') {
            return (int) $left[$field] <=> (int) $right[$field];
        }

        return strcasecmp((string) $left[$field], (string) $right[$field]);
    }

    public static function bubbleSort(array $items, string $field = 'full_name'): array
    {
        $count = count($items);
        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = 0; $j < $count - $i - 1; $j++) {
                if (self::compareByField($items[$j], $items[$j + 1], $field) > 0) {
                    [$items[$j], $items[$j + 1]] = [$items[$j + 1], $items[$j]];
                }
            }
        }

        return $items;
    }

    public static function insertionSort(array $items, string $field = 'full_name'): array
    {
        $count = count($items);
        for ($i = 1; $i < $count; $i++) {
            $current = $items[$i];
            $j = $i - 1;

            while ($j >= 0 && self::compareByField($items[$j], $current, $field) > 0) {
                $items[$j + 1] = $items[$j];
                $j--;
            }

            $items[$j + 1] = $current;
        }

        return $items;
    }

    public static function mergeSort(array $items, string $field = 'full_name'): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        $middle = intdiv(count($items), 2);
        $left = self::mergeSort(array_slice($items, 0, $middle), $field);
        $right = self::mergeSort(array_slice($items, $middle), $field);

        $merged = [];
        $leftIndex = 0;
        $rightIndex = 0;

        while ($leftIndex < count($left) && $rightIndex < count($right)) {
            if (self::compareByField($left[$leftIndex], $right[$rightIndex], $field) <= 0) {
                $merged[] = $left[$leftIndex++];
            } else {
                $merged[] = $right[$rightIndex++];
            }
        }

        return array_merge($merged, array_slice($left, $leftIndex), array_slice($right, $rightIndex));
    }

    public static function quickSort(array $items, string $field = 'full_name'): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        $pivot = $items[intdiv(count($items), 2)];
        $left = [];
        $right = [];
        $equal = [];

        foreach ($items as $item) {
            $comparison = self::compareByField($item, $pivot, $field);
            if ($comparison < 0) {
                $left[] = $item;
            } elseif ($comparison > 0) {
                $right[] = $item;
            } else {
                $equal[] = $item;
            }
        }

        return array_merge(
            self::quickSort($left, $field),
            $equal,
            self::quickSort($right, $field)
        );
    }

    public static function binarySearch(array $items, string $target, string $field = 'full_name'): int
    {
        $low = 0;
        $high = count($items) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $comparison = $field === 'BookingID'
                ? (int) $items[$middle][$field] <=> (int) $target
                : strcasecmp((string) $items[$middle][$field], $target);

            if ($comparison === 0) {
                return $middle;
            }

            if ($comparison < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return -1;
    }
}

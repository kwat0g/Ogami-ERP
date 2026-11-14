<?php

declare(strict_types=1);

namespace App\Common\DemoData;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

final class DemoRecordRegistry
{
    /** @var array<string, array{class: class-string<Model>, id: int|string}> */
    private array $records = [];

    public function remember(string $key, Model $record): void
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('A demo record registry key is required.');
        }

        $id = $record->getKey();
        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidArgumentException("Demo record {$key} must have a persisted scalar key.");
        }

        $current = $this->records[$key] ?? null;
        if ($current !== null && ($current['class'] !== $record::class || $current['id'] !== $id)) {
            throw new LogicException("Demo record registry key {$key} already points to another record.");
        }

        $this->records[$key] = ['class' => $record::class, 'id' => $id];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->records);
    }

    public function id(string $key): int|string
    {
        return $this->record($key)['id'];
    }

    /** @return class-string<Model> */
    public function modelClass(string $key): string
    {
        return $this->record($key)['class'];
    }

    /** @return array{class: class-string<Model>, id: int|string} */
    private function record(string $key): array
    {
        return $this->records[$key] ?? throw new InvalidArgumentException("Demo record registry key {$key} is not registered.");
    }
}

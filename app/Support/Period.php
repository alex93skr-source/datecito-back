<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rango de fechas [from, to) y granularidad para las métricas del CRM.
 */
final class Period
{
    public const INTERVALS = ['day', 'week', 'month'];

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $interval = 'day',
    ) {}

    /**
     * Últimos N días completos hasta hoy (incluido).
     */
    public static function lastDays(int $days, string $interval = 'day'): self
    {
        $to = CarbonImmutable::tomorrow();

        return new self($to->subDays($days), $to, $interval);
    }

    public static function fromInput(?string $from, ?string $to, ?string $interval = null): self
    {
        $end = $to !== null ? CarbonImmutable::parse($to)->startOfDay()->addDay() : CarbonImmutable::tomorrow();
        $start = $from !== null ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays(30);

        if ($start->greaterThanOrEqualTo($end)) {
            $start = $end->subDay();
        }

        $days = $start->diffInDays($end);
        $interval ??= match (true) {
            $days > 180 => 'month',
            $days > 62 => 'week',
            default => 'day',
        };

        return new self($start, $end, in_array($interval, self::INTERVALS, true) ? $interval : 'day');
    }

    /**
     * Periodo inmediatamente anterior de la misma duración (para comparar).
     */
    public function previous(): self
    {
        $length = (int) $this->from->diffInSeconds($this->to);

        return new self($this->from->subSeconds($length), $this->from, $this->interval);
    }

    public function days(): int
    {
        return max(1, (int) $this->from->diffInDays($this->to));
    }

    /**
     * Expresión SQL (MySQL) que agrupa una columna por la granularidad del periodo.
     */
    public function bucket(string $column): string
    {
        $column = DB::getQueryGrammar()->wrap($column);

        return match ($this->interval) {
            'month' => "DATE_FORMAT($column, '%Y-%m-01')",
            'week' => "DATE(DATE_SUB($column, INTERVAL WEEKDAY($column) DAY))",
            default => "DATE($column)",
        };
    }

    /**
     * Todas las fechas de inicio de cubeta dentro del periodo, para rellenar ceros.
     *
     * @return list<string>
     */
    public function buckets(): array
    {
        $cursor = match ($this->interval) {
            'month' => $this->from->startOfMonth(),
            'week' => $this->from->startOfWeek(),
            default => $this->from->startOfDay(),
        };
        $keys = [];

        while ($cursor->lessThan($this->to)) {
            $keys[] = $cursor->toDateString();
            $cursor = match ($this->interval) {
                'month' => $cursor->addMonth(),
                'week' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $keys;
    }

    /**
     * Convierte filas {bucket, value} en una serie completa con ceros donde no hubo datos.
     *
     * @param  iterable<int, object>  $rows
     * @return list<array{date: string, value: float|int}>
     */
    public function series(iterable $rows, bool $float = false): array
    {
        $byBucket = [];
        foreach ($rows as $row) {
            $byBucket[substr((string) $row->bucket, 0, 10)] = $float ? round((float) $row->value, 2) : (int) $row->value;
        }

        return array_map(fn (string $date) => ['date' => $date, 'value' => $byBucket[$date] ?? 0], $this->buckets());
    }

    /**
     * @return array{from: string, to: string, interval: string, days: int}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->subDay()->toDateString(),
            'interval' => $this->interval,
            'days' => $this->days(),
        ];
    }
}

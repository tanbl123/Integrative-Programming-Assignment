<?php
/**
 * Interchangeable bin-selection strategies.
 * Author : Ong Kar Heng (2408830)
 * Module : Collection Scheduling & Assignment - Strategy design pattern
 */
interface BinSelectionStrategy
{
    public function name(): string;
    public function select(): array;
}

class FullBinSelectionStrategy implements BinSelectionStrategy
{
    public function name(): string { return 'Full Bins'; }
    public function select(): array
    {
        return array_map(static fn(Bin $bin): array => [
            'bin' => $bin, 'complaint_id' => null, 'priority' => 'Urgent', 'reason' => 'Bin is marked Full.',
        ], Bin::search('', Bin::STATUS_FULL, null, false));
    }
}

class ComplaintPrioritySelectionStrategy implements BinSelectionStrategy
{
    public function name(): string { return 'Complaint Priority'; }
    public function select(): array
    {
        $selected = [];
        foreach (Complaint::unresolved() as $complaint) {
            $bin = $complaint->getBin();
            if ($bin !== null && $bin->isActive() && !isset($selected[$bin->getKey()])) {
                $selected[$bin->getKey()] = [
                    'bin' => $bin,
                    'complaint_id' => $complaint->getKey(),
                    'priority' => 'Urgent',
                    'reason' => 'Unresolved complaint #' . $complaint->getKey() . ': ' . $complaint->getType(),
                ];
            }
        }
        return array_values($selected);
    }
}

class RoutineBinSelectionStrategy implements BinSelectionStrategy
{
    public function name(): string { return 'Routine'; }
    public function select(): array
    {
        return array_map(static fn(Bin $bin): array => [
            'bin' => $bin, 'complaint_id' => null, 'priority' => 'Routine', 'reason' => 'Fixed routine collection.',
        ], Bin::findActive());
    }
}

class SchedulingStrategyFactory
{
    public static function names(): array { return ['Full Bins', 'Complaint Priority', 'Routine']; }
    public static function make(string $name): BinSelectionStrategy
    {
        return match ($name) {
            'Full Bins' => new FullBinSelectionStrategy(),
            'Complaint Priority' => new ComplaintPrioritySelectionStrategy(),
            'Routine' => new RoutineBinSelectionStrategy(),
            default => throw new InvalidArgumentException('Unknown scheduling strategy.'),
        };
    }
}

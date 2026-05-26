<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Scheduling;

use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\LessonRequest;
use Roostar\Modules\Rosters\Engine\Model\Room;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Model\TimeSlot;

final class GreedyInitialScheduler implements InitialScheduler
{
    private array $teacherSlots = [];
    private array $classSlots = [];
    private array $roomSlots = [];
    private array $classSubjectDayPeriods = [];
    private array $teacherDayRooms = [];
    private array $classDayPeriods = [];
    private array $classLessonTotals = [];

    public function createInitialSchedule(SchedulingInput $input): Schedule
    {
        $schedule = new Schedule();
        $this->resetState($input);

        foreach ($this->orderedLessonRequests($input->lessonRequests, $input) as $lessonRequest) {
            $assignment = $this->firstAvailableAssignment($lessonRequest, $input, $schedule);

            if ($assignment) {
                $schedule = $schedule->withAssignment($assignment);
                $this->indexAssignment($assignment, $input);
            }
        }

        return $schedule;
    }

    private function resetState(SchedulingInput $input): void
    {
        $this->teacherSlots = [];
        $this->classSlots = [];
        $this->roomSlots = [];
        $this->classSubjectDayPeriods = [];
        $this->teacherDayRooms = [];
        $this->classDayPeriods = [];
        $this->classLessonTotals = [];

        foreach ($input->lessonRequests as $lessonRequest) {
            $this->classLessonTotals[$lessonRequest->classGroupId] = ($this->classLessonTotals[$lessonRequest->classGroupId] ?? 0) + 1;
        }
    }

    /**
     * @param LessonRequest[] $lessonRequests
     * @return LessonRequest[]
     */
    private function orderedLessonRequests(array $lessonRequests, SchedulingInput $input): array
    {
        usort($lessonRequests, function (LessonRequest $a, LessonRequest $b) use ($input): int {
            if ($a->allowBlockHours !== $b->allowBlockHours) {
                return $a->allowBlockHours ? -1 : 1;
            }

            $aDifficulty = $this->requestDifficulty($a, $input);
            $bDifficulty = $this->requestDifficulty($b, $input);

            return $aDifficulty === $bDifficulty
                ? [$a->classGroupId, $a->subjectId, $a->id] <=> [$b->classGroupId, $b->subjectId, $b->id]
                : $bDifficulty <=> $aDifficulty;
        });

        return $lessonRequests;
    }

    private function requestDifficulty(LessonRequest $lessonRequest, SchedulingInput $input): int
    {
        $teacher = $input->teacher($lessonRequest->teacherId);
        $slotCount = $lessonRequest->allowedSlotIds === []
            ? count($input->timeSlots)
            : count($lessonRequest->allowedSlotIds);
        $roomCount = $lessonRequest->allowedRoomIds === []
            ? count($input->rooms)
            : count($lessonRequest->allowedRoomIds);

        if ($teacher && $teacher->availableSlotIds !== []) {
            $slotCount = min($slotCount, count($teacher->availableSlotIds));
        }

        return (int) (100000 / max(1, $slotCount * max(1, $roomCount)));
    }

    private function firstAvailableAssignment(LessonRequest $lessonRequest, SchedulingInput $input, Schedule $schedule): ?LessonAssignment
    {
        $bestAssignment = null;
        $bestScore = PHP_INT_MIN;

        foreach ($this->orderedSlotsForRequest($lessonRequest, $input, $schedule) as $slot) {
            if (!$this->slotAllowed($lessonRequest, $input, $slot)) {
                continue;
            }

            foreach ($input->rooms as $room) {
                if (!$this->roomAllowed($lessonRequest, $room, $slot)) {
                    continue;
                }

                if (!$this->slotHasFreeCoreResources($lessonRequest, $room, $slot, $input)) {
                    continue;
                }

                $candidate = new LessonAssignment(
                    $lessonRequest->id,
                    $lessonRequest->classGroupId,
                    $lessonRequest->teacherId,
                    $lessonRequest->subjectId,
                    $room->id,
                    $slot->id,
                );
                $score = $this->candidateScore($candidate, $lessonRequest, $room, $slot, $input);

                if ($score > $bestScore) {
                    $bestAssignment = $candidate;
                    $bestScore = $score;
                }
            }
        }

        return $bestAssignment;
    }

    /**
     * @return TimeSlot[]
     */
    private function orderedSlotsForRequest(LessonRequest $lessonRequest, SchedulingInput $input, Schedule $schedule): array
    {
        if (!$lessonRequest->allowBlockHours) {
            return $input->timeSlots;
        }

        $sameSubjectAssignments = array_values(array_filter(
            $schedule->assignments(),
            static fn (LessonAssignment $assignment): bool => $assignment->classGroupId === $lessonRequest->classGroupId
                && $assignment->subjectId === $lessonRequest->subjectId,
        ));

        if (count($sameSubjectAssignments) % 2 === 0) {
            return $input->timeSlots;
        }

        $anchorAssignment = $sameSubjectAssignments[array_key_last($sameSubjectAssignments)] ?? null;
        $anchorSlot = $anchorAssignment ? $input->slot($anchorAssignment->slotId) : null;

        if (!$anchorSlot) {
            return $input->timeSlots;
        }

        $adjacent = [];
        $remaining = [];

        foreach ($input->timeSlots as $slot) {
            if ($slot->dayIndex === $anchorSlot->dayIndex && abs($slot->period - $anchorSlot->period) === 1) {
                $adjacent[] = $slot;
                continue;
            }

            $remaining[] = $slot;
        }

        usort($adjacent, static fn (TimeSlot $a, TimeSlot $b): int => abs($a->period - $anchorSlot->period) <=> abs($b->period - $anchorSlot->period));

        return $adjacent === [] ? $input->timeSlots : [...$adjacent, ...$remaining];
    }

    private function slotAllowed(LessonRequest $lessonRequest, SchedulingInput $input, TimeSlot $slot): bool
    {
        $teacher = $input->teacher($lessonRequest->teacherId);
        $classGroup = $input->classGroup($lessonRequest->classGroupId);

        if ($lessonRequest->allowedSlotIds !== [] && !in_array($slot->id, $lessonRequest->allowedSlotIds, true)) {
            return false;
        }

        if ($teacher && $teacher->availableSlotIds !== [] && !in_array($slot->id, $teacher->availableSlotIds, true)) {
            return false;
        }

        if ($classGroup && $classGroup->availableSlotIds !== [] && !in_array($slot->id, $classGroup->availableSlotIds, true)) {
            return false;
        }

        return true;
    }

    private function roomAllowed(LessonRequest $lessonRequest, Room $room, TimeSlot $slot): bool
    {
        if ($lessonRequest->allowedRoomIds !== [] && !in_array($room->id, $lessonRequest->allowedRoomIds, true)) {
            return false;
        }

        return $room->availableSlotIds === [] || in_array($slot->id, $room->availableSlotIds, true);
    }

    private function slotHasFreeCoreResources(LessonRequest $lessonRequest, Room $room, TimeSlot $slot, SchedulingInput $input): bool
    {
        if (
            isset($this->teacherSlots[$lessonRequest->teacherId][$slot->id])
            || isset($this->classSlots[$lessonRequest->classGroupId][$slot->id])
            || isset($this->roomSlots[$room->id][$slot->id])
        ) {
            return false;
        }

        $periods = $this->classSubjectDayPeriods[$lessonRequest->classGroupId][$lessonRequest->subjectId][$slot->dayIndex] ?? [];

        if (!$lessonRequest->allowBlockHours) {
            foreach ($periods as $period) {
                if (abs((int) $period - $slot->period) === 1) {
                    return false;
                }
            }

            return true;
        }

        if (count($periods) % 2 === 1) {
            foreach ($periods as $period) {
                if (abs((int) $period - $slot->period) === 1) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function candidateScore(LessonAssignment $assignment, LessonRequest $request, Room $room, TimeSlot $slot, SchedulingInput $input): int
    {
        $score = 1000;
        $sameSubjectPeriods = $this->classSubjectDayPeriods[$request->classGroupId][$request->subjectId][$slot->dayIndex] ?? [];
        $classDayPeriods = $this->classDayPeriods[$request->classGroupId][$slot->dayIndex] ?? [];

        if ($request->allowBlockHours) {
            $score += count($sameSubjectPeriods) % 2 === 1 ? 350 : 80;

            if (count($sameSubjectPeriods) % 2 === 0 && !$this->hasPossibleBlockNeighbour($request, $room, $slot, $input)) {
                $score -= 240;
            }
        } elseif ($sameSubjectPeriods !== []) {
            $score -= 140 * count($sameSubjectPeriods);
        }

        $score += $this->classCompactnessScore($classDayPeriods, $slot->period);
        $score += $this->classDayBalanceScore($request->classGroupId, $slot);
        $score += $this->teacherMoveScore($assignment, $room, $slot);

        $teacher = $input->teacher($request->teacherId);
        if ($teacher && isset($teacher->preferredRoomIds[$room->id])) {
            $score += 60 + (int) $teacher->preferredRoomIds[$room->id];
        }

        $score -= $slot->period;

        return $score;
    }

    private function classDayBalanceScore(string $classGroupId, TimeSlot $slot): int
    {
        $total = (int) ($this->classLessonTotals[$classGroupId] ?? 0);

        if ($total < 1) {
            return 0;
        }

        $current = count($this->classDayPeriods[$classGroupId][$slot->dayIndex] ?? []);
        $target = $this->targetDayLoad($total, $slot->dayIndex);
        $beforeDistance = abs($current - $target);
        $afterDistance = abs(($current + 1) - $target);
        $score = (int) round(($beforeDistance - $afterDistance) * 180);

        if ($current === 0 && $slot->dayIndex <= 4 && $total >= 10) {
            $score += 120;
        }

        if ($current + 1 > $target + 0.35) {
            $score -= (int) round((($current + 1) - $target) * 90);
        }

        if ($slot->dayIndex === 5) {
            $score -= 70;

            if ($slot->period >= 5) {
                $score -= 220;
            }

            if ($slot->period >= 7) {
                $score -= 180;
            }
        } elseif ($slot->period >= 8) {
            $score -= 70;
        }

        return $score;
    }

    private function targetDayLoad(int $total, int $dayIndex): float
    {
        $weights = [
            1 => 1.08,
            2 => 1.08,
            3 => 1.08,
            4 => 1.02,
            5 => 0.74,
        ];
        $sum = array_sum($weights);

        return $total * ($weights[$dayIndex] ?? 1.0) / $sum;
    }

    private function hasPossibleBlockNeighbour(LessonRequest $request, Room $room, TimeSlot $slot, SchedulingInput $input): bool
    {
        foreach ($input->timeSlots as $candidate) {
            if ($candidate->dayIndex !== $slot->dayIndex || abs($candidate->period - $slot->period) !== 1) {
                continue;
            }

            if (!$this->slotAllowed($request, $input, $candidate)) {
                continue;
            }

            if (
                isset($this->teacherSlots[$request->teacherId][$candidate->id])
                || isset($this->classSlots[$request->classGroupId][$candidate->id])
                || isset($this->roomSlots[$room->id][$candidate->id])
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function classCompactnessScore(array $periods, int $period): int
    {
        if ($periods === []) {
            return 0;
        }

        $periods = array_values(array_unique(array_map('intval', $periods)));
        sort($periods);

        $score = 0;
        $min = min($periods);
        $max = max($periods);

        if (in_array($period - 1, $periods, true) || in_array($period + 1, $periods, true)) {
            $score += 70;
        }

        if ($period > $min && $period < $max && !in_array($period, $periods, true)) {
            $score += 45;
        }

        if ($period < $min - 1 || $period > $max + 1) {
            $score -= 35;
        }

        return $score;
    }

    private function teacherMoveScore(LessonAssignment $assignment, Room $room, TimeSlot $slot): int
    {
        $score = 0;
        $roomsByPeriod = $this->teacherDayRooms[$assignment->teacherId][$slot->dayIndex] ?? [];

        foreach ([$slot->period - 1, $slot->period + 1] as $period) {
            if (!isset($roomsByPeriod[$period])) {
                continue;
            }

            $score += $roomsByPeriod[$period] === $room->id ? 35 : -25;
        }

        return $score;
    }

    private function indexAssignment(LessonAssignment $assignment, SchedulingInput $input): void
    {
        $slot = $input->slot($assignment->slotId);

        if (!$slot) {
            return;
        }

        $this->teacherSlots[$assignment->teacherId][$slot->id] = true;
        $this->classSlots[$assignment->classGroupId][$slot->id] = true;
        $this->roomSlots[$assignment->roomId][$slot->id] = true;
        $this->classSubjectDayPeriods[$assignment->classGroupId][$assignment->subjectId][$slot->dayIndex][] = $slot->period;
        $this->teacherDayRooms[$assignment->teacherId][$slot->dayIndex][$slot->period] = $assignment->roomId;
        $this->classDayPeriods[$assignment->classGroupId][$slot->dayIndex][] = $slot->period;
    }
}

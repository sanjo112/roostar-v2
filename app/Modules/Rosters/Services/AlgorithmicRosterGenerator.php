<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Services;

final class AlgorithmicRosterGenerator
{
    public function generate(array $constraints): array
    {
        $slots = $this->slots();
        $lessonGroups = array_values(array_filter(
            $constraints['lessonGroups'] ?? [],
            static fn (array $group): bool => (int) ($group['hoursPerWeek'] ?? 0) > 0,
        ));

        usort($lessonGroups, function (array $a, array $b) use ($constraints): int {
            $aOptions = max(1, count($this->qualifiedTeachers($a, $constraints)) * count($this->suitableRooms($a, $constraints)));
            $bOptions = max(1, count($this->qualifiedTeachers($b, $constraints)) * count($this->suitableRooms($b, $constraints)));

            return $aOptions === $bOptions
                ? (int) ($b['hoursPerWeek'] ?? 0) <=> (int) ($a['hoursPerWeek'] ?? 0)
                : $aOptions <=> $bOptions;
        });

        $lessons = [];
        $issues = [];
        $teacherSlot = [];
        $roomSlot = [];
        $classSlot = [];
        $groupDayHours = [];
        $teacherDayHours = [];
        $teacherWeekHours = [];

        foreach ($lessonGroups as $group) {
            for ($index = 0; $index < (int) $group['hoursPerWeek']; $index++) {
                $placement = $this->bestPlacement(
                    $group,
                    $constraints,
                    $slots,
                    $teacherSlot,
                    $roomSlot,
                    $classSlot,
                    $groupDayHours,
                    $teacherDayHours,
                    $teacherWeekHours,
                );

                if ($placement === null) {
                    $issues[] = 'Niet geplaatst: ' . (string) $group['subject']['code'] . ' voor ' . (string) $group['className'];
                    continue;
                }

                $lesson = [
                    'lessonGroup' => $group,
                    'teacher' => $placement['teacher'],
                    'room' => $placement['room'],
                    'slot' => $placement['slot'],
                ];
                $lessons[] = $lesson;

                $slotKey = $this->slotKey($placement['slot']);
                $day = $placement['slot']['day'];
                $period = (int) $placement['slot']['period'];
                $teacherSlot[$placement['teacher']['id'] . '_' . $slotKey] = true;
                $roomSlot[$placement['room']['id'] . '_' . $slotKey] = true;
                $classSlot[$group['classId'] . '_' . $slotKey] = true;
                $groupDayHours[$group['id']][$day][] = $period;
                $teacherDayHours[$placement['teacher']['id']][$day] = ($teacherDayHours[$placement['teacher']['id']][$day] ?? 0) + 1;
                $teacherWeekHours[$placement['teacher']['id']] = ($teacherWeekHours[$placement['teacher']['id']] ?? 0) + 1;
            }
        }

        return [
            'success' => true,
            'lessons' => $lessons,
            'issues' => array_values(array_unique($issues)),
            'stats' => [
                'lessonGroups' => count($lessonGroups),
                'lessons' => count($lessons),
                'unplaced' => count($issues),
            ],
        ];
    }

    private function bestPlacement(
        array $group,
        array $constraints,
        array $slots,
        array $teacherSlot,
        array $roomSlot,
        array $classSlot,
        array $groupDayHours,
        array $teacherDayHours,
        array $teacherWeekHours,
    ): ?array {
        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($slots as $slot) {
            $slotKey = $this->slotKey($slot);

            if (isset($classSlot[$group['classId'] . '_' . $slotKey])) {
                continue;
            }

            foreach ($this->qualifiedTeachers($group, $constraints) as $teacher) {
                if (isset($teacherSlot[$teacher['id'] . '_' . $slotKey])) {
                    continue;
                }
                if (!$this->teacherAvailableForSlot($teacher, $slot)) {
                    continue;
                }
                if (($teacherWeekHours[$teacher['id']] ?? 0) >= (int) ($teacher['maxHoursPerWeek'] ?? 24)) {
                    continue;
                }
                if (($teacherDayHours[$teacher['id']][$slot['day']] ?? 0) >= (int) ($teacher['maxHoursPerDay'] ?? 6)) {
                    continue;
                }

                foreach ($this->suitableRooms($group, $constraints) as $room) {
                    if (isset($roomSlot[$room['id'] . '_' . $slotKey])) {
                        continue;
                    }
                    if (!$this->roomAvailableForSlot($room, $slot)) {
                        continue;
                    }

                    $score = $this->score($group, $room, $slot, $groupDayHours);
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $best = ['slot' => $slot, 'teacher' => $teacher, 'room' => $room];
                    }
                }
            }
        }

        return $best;
    }

    private function qualifiedTeachers(array $group, array $constraints): array
    {
        $subjectId = (string) $group['subject']['id'];
        $teachers = array_values(array_filter(
            $constraints['teachers'] ?? [],
            static fn (array $teacher): bool => in_array($subjectId, $teacher['subjectIds'] ?? [], true),
        ));

        usort($teachers, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $teachers;
    }

    private function suitableRooms(array $group, array $constraints): array
    {
        $subjectId = (string) $group['subject']['id'];
        $studentCount = (int) ($group['studentCount'] ?? 0);
        $rooms = array_values(array_filter($constraints['rooms'] ?? [], static function (array $room) use ($subjectId, $studentCount): bool {
            return in_array($subjectId, $room['subjectIds'] ?? [], true)
                && (int) ($room['capacity'] ?? 0) >= $studentCount;
        }));

        usort($rooms, static function (array $a, array $b) use ($studentCount): int {
            $aOver = (int) $a['capacity'] - $studentCount;
            $bOver = (int) $b['capacity'] - $studentCount;

            return $aOver === $bOver
                ? strcmp((string) $a['name'], (string) $b['name'])
                : $aOver <=> $bOver;
        });

        return $rooms;
    }

    private function teacherAvailableForSlot(array $teacher, array $slot): bool
    {
        $availableSlots = $teacher['availableSlots'] ?? null;

        if ($availableSlots === null) {
            return true;
        }

        if (!is_array($availableSlots)) {
            return false;
        }

        return in_array($this->slotKey($slot), $availableSlots, true);
    }

    private function roomAvailableForSlot(array $room, array $slot): bool
    {
        if (empty($room['externalLocation'])) {
            return true;
        }

        $availableSlots = $room['availableSlots'] ?? null;

        if (!is_array($availableSlots)) {
            return false;
        }

        return in_array($this->slotKey($slot), $availableSlots, true);
    }

    private function score(array $group, array $room, array $slot, array $groupDayHours): int
    {
        $score = ((int) $slot['period']) * 8;
        $dayHours = array_values(array_unique(array_map('intval', $groupDayHours[$group['id']][$slot['day']] ?? [])));
        sort($dayHours);

        if (count($dayHours) >= 2) {
            return PHP_INT_MAX;
        }

        if (count($dayHours) === 1) {
            $score += abs($dayHours[0] - (int) $slot['period']) === 1 ? 20 : 180;
        }

        $score += max(0, (int) $room['capacity'] - (int) ($group['studentCount'] ?? 0));

        return $score;
    }

    private function slots(): array
    {
        $times = [
            1 => ['08:30', '09:20'],
            2 => ['09:20', '10:10'],
            3 => ['10:25', '11:15'],
            4 => ['11:15', '12:05'],
            5 => ['12:45', '13:35'],
            6 => ['13:35', '14:25'],
            7 => ['14:25', '15:15'],
            8 => ['15:15', '16:05'],
            9 => ['16:05', '16:55'],
        ];
        $slots = [];

        foreach (['ma' => 'Maandag', 'di' => 'Dinsdag', 'wo' => 'Woensdag', 'do' => 'Donderdag', 'vr' => 'Vrijdag'] as $key => $label) {
            foreach ($times as $period => $range) {
                $slots[] = [
                    'dayKey' => $key,
                    'day' => $label,
                    'period' => $period,
                    'start' => $range[0],
                    'end' => $range[1],
                ];
            }
        }

        return $slots;
    }

    private function slotKey(array $slot): string
    {
        return (string) $slot['dayKey'] . '-' . (string) $slot['period'];
    }
}

<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Services;

final class RosterValidator
{
    public function validate(array $constraints, array $result): array
    {
        $errors = [];
        $teacherSlots = [];
        $roomSlots = [];
        $classSlots = [];
        $hoursByGroup = [];
        $teacherDayHours = [];
        $teacherWeekHours = [];
        $classSubjectDayPeriods = [];

        foreach ($result['lessons'] ?? [] as $lesson) {
            $slotKey = (string) $lesson['slot']['dayKey'] . '-' . (string) $lesson['slot']['period'];
            $day = (string) $lesson['slot']['day'];
            $group = $lesson['lessonGroup'];
            $teacher = $lesson['teacher'];
            $room = $lesson['room'];

            $teacherKey = $teacher['id'] . '_' . $slotKey;
            $roomKey = $room['id'] . '_' . $slotKey;
            $classKey = $group['classId'] . '_' . $slotKey;

            if (isset($teacherSlots[$teacherKey])) {
                $errors[] = 'Dubbele boeking leraar: ' . $teacher['name'] . ' op ' . $slotKey;
            }
            if (isset($roomSlots[$roomKey])) {
                $errors[] = 'Dubbele boeking lokaal: ' . $room['name'] . ' op ' . $slotKey;
            }
            if (isset($classSlots[$classKey])) {
                $errors[] = 'Dubbele les voor klas op ' . $slotKey;
            }

            $teacherSlots[$teacherKey] = true;
            $roomSlots[$roomKey] = true;
            $classSlots[$classKey] = true;
            $hoursByGroup[$group['id']] = ($hoursByGroup[$group['id']] ?? 0) + 1;
            $teacherDayHours[$teacher['id']][$day] = ($teacherDayHours[$teacher['id']][$day] ?? 0) + 1;
            $teacherWeekHours[$teacher['id']] = ($teacherWeekHours[$teacher['id']] ?? 0) + 1;
            $classSubjectDayPeriods[$group['classId']][$group['subject']['id']][$day][] = (int) $lesson['slot']['period'];

            if (!in_array($group['subject']['id'], $teacher['subjectIds'] ?? [], true)) {
                $errors[] = $teacher['name'] . ' is niet bevoegd voor ' . $group['subject']['code'];
            }
            if (!$this->availableForSlot($teacher['availableSlots'] ?? null, $slotKey)) {
                $errors[] = $teacher['name'] . ' werkt niet op ' . $slotKey;
            }
            if (($teacherDayHours[$teacher['id']][$day] ?? 0) > (int) ($teacher['maxHoursPerDay'] ?? 6)) {
                $errors[] = $teacher['name'] . ' heeft te veel uren op ' . $day;
            }
            if (($teacherWeekHours[$teacher['id']] ?? 0) > (int) ($teacher['maxHoursPerWeek'] ?? 24)) {
                $errors[] = $teacher['name'] . ' heeft te veel uren in de week.';
            }
            if (!in_array($group['subject']['id'], $room['subjectIds'] ?? [], true)) {
                $errors[] = $room['name'] . ' is niet geschikt voor ' . $group['subject']['code'];
            }
            if (!empty($room['externalLocation']) && !$this->availableForSlot($room['availableSlots'] ?? null, $slotKey)) {
                $errors[] = $room['name'] . ' is niet inzetbaar op ' . $slotKey;
            }
            if ((int) ($room['capacity'] ?? 0) < (int) ($group['studentCount'] ?? 0)) {
                $errors[] = $room['name'] . ' heeft te weinig capaciteit voor ' . $group['subject']['code'] . ' ' . $group['className'];
            }
        }

        foreach ($constraints['lessonGroups'] ?? [] as $group) {
            $planned = $hoursByGroup[$group['id']] ?? 0;
            if ($planned !== (int) $group['hoursPerWeek']) {
                $errors[] = $group['subject']['code'] . ' heeft ' . $planned . ' van ' . (int) $group['hoursPerWeek'] . ' lessen gepland.';
            }

            $periodsByDay = $classSubjectDayPeriods[$group['classId']][$group['subject']['id']] ?? [];

            if (!empty($group['allowBlockHours'])) {
                $requiredPairs = intdiv((int) $group['hoursPerWeek'], 2);
                $plannedPairs = 0;

                foreach ($periodsByDay as $periods) {
                    $plannedPairs += $this->countBlockPairs($periods);
                }

                if ($requiredPairs > 0 && $plannedPairs < $requiredPairs) {
                    $errors[] = $group['subject']['code'] . ' ' . $group['className'] . ' moet als blokuur worden ingepland.';
                }

                continue;
            }

            foreach ($periodsByDay as $day => $periods) {
                $periods = array_values(array_unique(array_map('intval', $periods)));
                sort($periods);

                for ($index = 1; $index < count($periods); $index++) {
                    if ($periods[$index] === $periods[$index - 1] + 1) {
                        $errors[] = $group['subject']['code'] . ' ' . $group['className'] . ' heeft een blokuur op ' . $day . ', maar blokuur is niet toegestaan.';
                    }
                }
            }
        }

        return [
            'success' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function availableForSlot(mixed $availableSlots, string $slotKey): bool
    {
        if ($availableSlots === null) {
            return true;
        }

        return is_array($availableSlots) && in_array($slotKey, $availableSlots, true);
    }

    private function countBlockPairs(array $periods): int
    {
        $periods = array_values(array_unique(array_map('intval', $periods)));
        sort($periods);

        $pairs = 0;

        for ($index = 1; $index < count($periods); $index++) {
            if ($periods[$index] !== $periods[$index - 1] + 1) {
                continue;
            }

            $pairs++;
            $index++;
        }

        return $pairs;
    }
}

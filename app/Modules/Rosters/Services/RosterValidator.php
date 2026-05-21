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

        foreach ($result['lessons'] ?? [] as $lesson) {
            $slotKey = (string) $lesson['slot']['dayKey'] . '-' . (string) $lesson['slot']['period'];
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

            if (!in_array($group['subject']['id'], $teacher['subjectIds'] ?? [], true)) {
                $errors[] = $teacher['name'] . ' is niet bevoegd voor ' . $group['subject']['code'];
            }
            if (!in_array($group['subject']['id'], $room['subjectIds'] ?? [], true)) {
                $errors[] = $room['name'] . ' is niet geschikt voor ' . $group['subject']['code'];
            }
        }

        foreach ($constraints['lessonGroups'] ?? [] as $group) {
            $planned = $hoursByGroup[$group['id']] ?? 0;
            if ($planned !== (int) $group['hoursPerWeek']) {
                $errors[] = $group['subject']['code'] . ' heeft ' . $planned . ' van ' . (int) $group['hoursPerWeek'] . ' lessen gepland.';
            }
        }

        return [
            'success' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }
}

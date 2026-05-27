<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Services;

use Roostar\Modules\Rosters\Engine\Model\ClassGroup;
use Roostar\Modules\Rosters\Engine\Model\LessonRequest;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Model\Room;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Model\Subject;
use Roostar\Modules\Rosters\Engine\Model\Teacher;
use Roostar\Modules\Rosters\Engine\Model\TimeSlot;
use Roostar\Modules\Rosters\Engine\SchedulingEngineFactory;

final class EngineRosterGenerator
{
    public function generate(array $constraints): array
    {
        $adapter = $this->inputFromConstraints($constraints);
        $input = $adapter['input'];
        $requestGroups = $adapter['requestGroups'];
        $slots = $adapter['slots'];
        $teachers = $adapter['teachers'];
        $rooms = $adapter['rooms'];
        $issues = $adapter['issues'];

        $run = SchedulingEngineFactory::default()->run($input);
        $assigned = [];
        $lessons = [];

        foreach ($run->schedule->assignments() as $assignment) {
            $group = $requestGroups[$assignment->lessonRequestId] ?? null;
            $slot = $slots[$assignment->slotId] ?? null;
            $teacher = $teachers[$assignment->teacherId] ?? null;
            $room = $rooms[$assignment->roomId] ?? null;

            if ($group === null || $slot === null || $teacher === null || $room === null) {
                continue;
            }

            $assigned[$assignment->lessonRequestId] = true;
            $lessons[] = [
                'lessonGroup' => $group,
                'teacher' => $teacher,
                'room' => $room,
                'slot' => $slot,
            ];
        }

        foreach ($requestGroups as $requestId => $group) {
            if (!isset($assigned[$requestId])) {
                $request = $input->lessonRequest((string) $requestId);
                $issues[] = $this->unassignedRequestIssue($request, $group, $input, $run->schedule);
            }
        }

        foreach ($run->explanations as $explanation) {
            if (($explanation['severity'] ?? '') === 'hard') {
                $issues[] = (string) ($explanation['message'] ?? 'Harde roosterregel niet gehaald.');
            }
        }

        if (!empty($constraints['calendar']['breaks'])) {
            $issues[] = count($constraints['calendar']['breaks']) . ' vrije dagen/vakanties worden per week toegepast in het weekrooster.';
        }

        if (!empty($constraints['calendar']['testWeeks'])) {
            $issues[] = count($constraints['calendar']['testWeeks']) . ' toetsweek(en) beperken het weekrooster in de betreffende week.';
        }

        return [
            'success' => $run->score->validation->isValid(),
            'lessons' => $lessons,
            'issues' => array_values(array_unique($issues)),
            'stats' => [
                'lessonGroups' => count($constraints['lessonGroups'] ?? []),
                'lessonRequests' => (int) $adapter['expectedRequests'],
                'lessons' => count($lessons),
                'unplaced' => max(0, (int) $adapter['expectedRequests'] - count($lessons)),
                'skippedRequests' => (int) $adapter['skippedRequests'],
                'engineScore' => $run->score->value,
                'hardViolations' => $run->score->validation->hardCount(),
                'softViolations' => $run->score->validation->softCount(),
            ],
            'engine' => [
                'steps' => $run->steps,
                'explanations' => $run->explanations,
            ],
        ];
    }

    private function inputFromConstraints(array $constraints): array
    {
        $slots = $this->slots();
        $classGroups = [];
        $subjects = [];
        $rooms = [];
        $teachers = [];
        $requestGroups = [];
        $lessonRequests = [];
        $issues = [];
        $teacherLoad = [];
        $teacherSubjectLoad = [];
        $expectedRequests = 0;
        $skippedRequests = 0;

        foreach ($constraints['classes'] ?? [] as $class) {
            $classGroups[] = new ClassGroup((string) $class['id'], (string) $class['naam']);
        }

        foreach ($constraints['lessonGroups'] ?? [] as $group) {
            $subjects[(string) $group['subject']['id']] = new Subject(
                (string) $group['subject']['id'],
                (string) $group['subject']['name'],
                (string) $group['subject']['code'],
            );
        }

        foreach ($constraints['teachers'] ?? [] as $teacher) {
            $teachers[(string) $teacher['id']] = $teacher;
        }

        foreach ($constraints['rooms'] ?? [] as $room) {
            $rooms[(string) $room['id']] = $room;
        }

        foreach ($this->orderedLessonGroups($constraints) as $group) {
            $qualifiedTeachers = $this->qualifiedTeachers($group, $constraints);
            $suitableRooms = $this->suitableRooms($group, $constraints);
            $hoursPerWeek = (int) ($group['hoursPerWeek'] ?? 0);
            $expectedRequests += max(0, $hoursPerWeek);

            if ($qualifiedTeachers === [] || $suitableRooms === []) {
                $issues[] = $this->unplaceableGroupIssue($group, $qualifiedTeachers, $suitableRooms);
                $skippedRequests += max(0, $hoursPerWeek);
                continue;
            }

            $teacher = $this->selectTeacherForGroup($group, $qualifiedTeachers, $teacherLoad, $teacherSubjectLoad);

            if ($teacher === null) {
                $issues[] = 'Niet geplaatst: ' . (string) $group['subject']['code'] . ' voor ' . (string) $group['className'] . '. Geen bevoegde leraar heeft genoeg ruimte om dit vak volledig aan deze klas te geven.';
                $skippedRequests += max(0, $hoursPerWeek);
                continue;
            }

            $teacherId = (string) $teacher['id'];
            $subjectId = (string) $group['subject']['id'];
            $teacherLoad[$teacherId] = ($teacherLoad[$teacherId] ?? 0) + $hoursPerWeek;
            $teacherSubjectLoad[$teacherId][$subjectId] = ($teacherSubjectLoad[$teacherId][$subjectId] ?? 0) + $hoursPerWeek;

            for ($hour = 1; $hour <= $hoursPerWeek; $hour++) {
                $requestId = (string) $group['id'] . '#' . $hour;
                $requestGroups[$requestId] = $group;
                $lessonRequests[] = new LessonRequest(
                    $requestId,
                    (string) $group['classId'],
                    $teacherId,
                    $subjectId,
                    allowedRoomIds: array_map(static fn (array $room): string => (string) $room['id'], $suitableRooms),
                    allowBlockHours: !empty($group['allowBlockHours']),
                );
            }
        }

        return [
            'input' => new SchedulingInput(
                array_map(static fn (array $slot): TimeSlot => new TimeSlot((string) $slot['id'], (int) $slot['dayIndex'], (int) $slot['period'], (string) $slot['label']), array_values($slots)),
                $classGroups,
                array_map(fn (array $teacher): Teacher => new Teacher(
                    (string) $teacher['id'],
                    (string) $teacher['name'],
                    $teacher['availableSlots'] === null ? [] : array_values(array_filter((array) $teacher['availableSlots'], 'is_string')),
                ), array_values($teachers)),
                array_values($subjects),
                array_map(fn (array $room): Room => new Room(
                    (string) $room['id'],
                    (string) $room['name'],
                    (int) ($room['capacity'] ?? 0),
                    (string) ($room['locationId'] ?? $room['id']),
                    !empty($room['externalLocation']) && is_array($room['availableSlots'] ?? null) ? array_values(array_filter((array) $room['availableSlots'], 'is_string')) : [],
                ), $this->orderedRooms($rooms)),
                $lessonRequests,
            ),
            'requestGroups' => $requestGroups,
            'slots' => $slots,
            'teachers' => $teachers,
            'rooms' => $rooms,
            'issues' => $issues,
            'expectedRequests' => $expectedRequests,
            'skippedRequests' => $skippedRequests,
        ];
    }

    private function orderedLessonGroups(array $constraints): array
    {
        $groups = array_values(array_filter(
            $constraints['lessonGroups'] ?? [],
            static fn (array $group): bool => (int) ($group['hoursPerWeek'] ?? 0) > 0,
        ));

        usort($groups, function (array $a, array $b) use ($constraints): int {
            $aOptions = max(1, count($this->qualifiedTeachers($a, $constraints)) * count($this->suitableRooms($a, $constraints)));
            $bOptions = max(1, count($this->qualifiedTeachers($b, $constraints)) * count($this->suitableRooms($b, $constraints)));

            return $aOptions === $bOptions
                ? (int) ($b['hoursPerWeek'] ?? 0) <=> (int) ($a['hoursPerWeek'] ?? 0)
                : $aOptions <=> $bOptions;
        });

        return $groups;
    }

    private function selectTeacherForGroup(array $group, array $teachers, array $teacherLoad, array $teacherSubjectLoad): ?array
    {
        $subjectId = (string) $group['subject']['id'];
        $hoursPerWeek = (int) ($group['hoursPerWeek'] ?? 0);
        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($teachers as $teacher) {
            $teacherId = (string) $teacher['id'];
            $maxHours = max(1, (int) ($teacher['maxHoursPerWeek'] ?? 24));
            $load = (int) ($teacherLoad[$teacherId] ?? 0);

            if ($load + $hoursPerWeek > $maxHours) {
                continue;
            }

            $preference = max(1, (int) ($teacher['subjectPreferences'][$subjectId] ?? 100));
            $subjectLoad = (int) ($teacherSubjectLoad[$teacherId][$subjectId] ?? 0);
            $projectedLoad = $load + $hoursPerWeek;
            $score = (int) round(($projectedLoad / $maxHours) * 1000) + ($subjectLoad * 80) - $preference;

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $teacher;
            }
        }

        return $best;
    }

    private function qualifiedTeachers(array $group, array $constraints): array
    {
        $subjectId = (string) $group['subject']['id'];

        return array_values(array_filter(
            $constraints['teachers'] ?? [],
            static fn (array $teacher): bool => in_array($subjectId, $teacher['subjectIds'] ?? [], true),
        ));
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

    private function orderedRooms(array $rooms): array
    {
        usort($rooms, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $rooms;
    }

    private function unplaceableGroupIssue(array $group, array $teachers, array $rooms): string
    {
        $prefix = 'Niet geplaatst: ' . (string) $group['subject']['code'] . ' voor ' . (string) $group['className'] . '. ';

        if ($teachers === [] && $rooms === []) {
            return $prefix . 'Geen bevoegde leraar en geen geschikt lokaal beschikbaar.';
        }

        if ($teachers === []) {
            return $prefix . 'Geen bevoegde leraar beschikbaar.';
        }

        return $prefix . 'Geen geschikt lokaal met voldoende capaciteit beschikbaar.';
    }

    private function unassignedRequestIssue(?LessonRequest $request, array $group, SchedulingInput $input, Schedule $schedule): string
    {
        $prefix = 'Niet geplaatst: ' . (string) $group['subject']['code'] . ' voor ' . (string) $group['className'] . '. ';

        if (!$request) {
            return $prefix . 'De lesaanvraag ontbreekt in de engine-output.';
        }

        $teacher = $input->teacher($request->teacherId);
        $classGroup = $input->classGroup($request->classGroupId);
        $allowedRooms = $this->allowedEngineRooms($request, $input);

        if ($teacher === null) {
            return $prefix . 'De gekozen docent bestaat niet meer in de roosterdata.';
        }

        if ($classGroup === null) {
            return $prefix . 'De klas bestaat niet meer in de roosterdata.';
        }

        if ($allowedRooms === []) {
            return $prefix . 'Er is geen geschikt lokaal beschikbaar voor dit vak en deze klasgrootte.';
        }

        $availabilityMatches = [];
        foreach ($input->timeSlots as $slot) {
            if ($request->allowedSlotIds !== [] && !in_array($slot->id, $request->allowedSlotIds, true)) {
                continue;
            }

            if ($teacher->availableSlotIds !== [] && !in_array($slot->id, $teacher->availableSlotIds, true)) {
                continue;
            }

            if ($classGroup->availableSlotIds !== [] && !in_array($slot->id, $classGroup->availableSlotIds, true)) {
                continue;
            }

            foreach ($allowedRooms as $room) {
                if ($room->availableSlotIds === [] || in_array($slot->id, $room->availableSlotIds, true)) {
                    $availabilityMatches[] = [$slot, $room];
                }
            }
        }

        if ($availabilityMatches === []) {
            return $prefix . 'Docent ' . $teacher->name . ', de klas en de geschikte lokalen hebben geen gezamenlijk beschikbaar moment.';
        }

        $teacherBusy = 0;
        $classBusy = 0;
        $roomBusy = 0;

        foreach ($availabilityMatches as [$slot, $room]) {
            foreach ($schedule->assignments() as $assignment) {
                if ($assignment->slotId !== $slot->id) {
                    continue;
                }

                $teacherBusy += $assignment->teacherId === $request->teacherId ? 1 : 0;
                $classBusy += $assignment->classGroupId === $request->classGroupId ? 1 : 0;
                $roomBusy += $assignment->roomId === $room->id ? 1 : 0;
            }
        }

        $reasons = [];
        if ($classBusy > 0) {
            $reasons[] = 'de klas is op passende momenten al bezet';
        }
        if ($teacherBusy > 0) {
            $reasons[] = 'docent ' . $teacher->name . ' is op passende momenten al bezet';
        }
        if ($roomBusy > 0) {
            $reasons[] = 'de geschikte lokalen zijn op passende momenten al bezet';
        }

        if ($reasons === []) {
            $reasons[] = !empty($group['allowBlockHours'])
                ? 'de blokuur-regels of spreidingsregels laten geen geldige plek over'
                : 'de spreidingsregels laten geen geldige plek over';
        }

        return $prefix . ucfirst(implode(', ', $reasons)) . '.';
    }

    /**
     * @return Room[]
     */
    private function allowedEngineRooms(LessonRequest $request, SchedulingInput $input): array
    {
        return array_values(array_filter(
            $input->rooms,
            static fn (Room $room): bool => $request->allowedRoomIds === [] || in_array($room->id, $request->allowedRoomIds, true),
        ));
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

        foreach ([1 => ['ma', 'Maandag'], 2 => ['di', 'Dinsdag'], 3 => ['wo', 'Woensdag'], 4 => ['do', 'Donderdag'], 5 => ['vr', 'Vrijdag']] as $dayIndex => [$dayKey, $day]) {
            foreach ($times as $period => $range) {
                $id = $dayKey . '-' . $period;
                $slots[$id] = [
                    'id' => $id,
                    'dayIndex' => $dayIndex,
                    'dayKey' => $dayKey,
                    'day' => $day,
                    'period' => $period,
                    'start' => $range[0],
                    'end' => $range[1],
                    'label' => $day . ' uur ' . $period,
                ];
            }
        }

        return $slots;
    }
}

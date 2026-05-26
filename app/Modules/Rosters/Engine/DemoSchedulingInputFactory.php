<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine;

use Roostar\Modules\Rosters\Engine\Model\ClassGroup;
use Roostar\Modules\Rosters\Engine\Model\LessonRequest;
use Roostar\Modules\Rosters\Engine\Model\Room;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Model\Subject;
use Roostar\Modules\Rosters\Engine\Model\Teacher;
use Roostar\Modules\Rosters\Engine\Model\TimeSlot;

final class DemoSchedulingInputFactory
{
    public static function create(): SchedulingInput
    {
        $slots = [];
        foreach ([1 => 'Ma', 2 => 'Di', 3 => 'Wo'] as $dayIndex => $dayLabel) {
            for ($period = 1; $period <= 4; $period++) {
                $slots[] = new TimeSlot("d{$dayIndex}p{$period}", $dayIndex, $period, "{$dayLabel} uur {$period}");
            }
        }

        return new SchedulingInput(
            $slots,
            [
                new ClassGroup('class-1a', '1A'),
                new ClassGroup('class-1b', '1B'),
            ],
            [
                new Teacher('teacher-jansen', 'Jansen', preferredRoomIds: ['room-a101' => 10]),
                new Teacher('teacher-pietersen', 'Pietersen', preferredRoomIds: ['room-a102' => 10]),
            ],
            [
                new Subject('subject-wis', 'Wiskunde', 'WIS'),
                new Subject('subject-nld', 'Nederlands', 'NLD'),
            ],
            [
                new Room('room-a101', 'A101', 28, 'gebouw-a'),
                new Room('room-a102', 'A102', 28, 'gebouw-a'),
                new Room('room-b201', 'B201', 28, 'gebouw-b'),
            ],
            [
                new LessonRequest('lesson-1', 'class-1a', 'teacher-jansen', 'subject-wis'),
                new LessonRequest('lesson-2', 'class-1a', 'teacher-pietersen', 'subject-nld'),
                new LessonRequest('lesson-3', 'class-1a', 'teacher-jansen', 'subject-wis'),
                new LessonRequest('lesson-4', 'class-1b', 'teacher-jansen', 'subject-wis'),
                new LessonRequest('lesson-5', 'class-1b', 'teacher-pietersen', 'subject-nld'),
                new LessonRequest('lesson-6', 'class-1b', 'teacher-pietersen', 'subject-nld'),
            ],
        );
    }
}


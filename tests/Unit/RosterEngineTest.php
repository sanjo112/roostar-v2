<?php

declare(strict_types=1);

use Roostar\Modules\Rosters\Engine\DemoSchedulingInputFactory;
use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\Schedule;
use Roostar\Modules\Rosters\Engine\Rules\Hard\TeacherNotDoubleBookedRule;
use Roostar\Modules\Rosters\Engine\SchedulingEngineFactory;

$input = DemoSchedulingInputFactory::create();
$result = SchedulingEngineFactory::default()->run($input);

assertRosterEngineTrue(
    $result->score->validation->isValid(),
    'De demo-engine moet een rooster zonder harde fouten kunnen maken.',
);

assertRosterEngineSame(
    count($input->lessonRequests),
    count($result->schedule->assignments()),
    'Alle demo-lesaanvragen moeten ingepland worden.',
);

assertRosterEngineTrue(
    count($result->steps) >= 5,
    'De engine moet een basisstap plus losse optimalisatie-stappen rapporteren.',
);

$conflictingSchedule = new Schedule([
    new LessonAssignment('lesson-a', 'class-1a', 'teacher-jansen', 'subject-wis', 'room-a101', 'd1p1'),
    new LessonAssignment('lesson-b', 'class-1b', 'teacher-jansen', 'subject-nld', 'room-a102', 'd1p1'),
]);

$teacherRuleResult = (new TeacherNotDoubleBookedRule())->validate($conflictingSchedule, $input);

assertRosterEngineFalse(
    $teacherRuleResult->isValid(),
    'Een docent mag niet dubbel geboekt kunnen worden.',
);

function assertRosterEngineTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertRosterEngineFalse(bool $condition, string $message): void
{
    assertRosterEngineTrue(!$condition, $message);
}

function assertRosterEngineSame(int $expected, int $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Expected {$expected}, got {$actual}.");
    }
}


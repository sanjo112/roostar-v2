<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine\Explanation;

use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Validation\ValidationResult;

final class ScheduleExplainer
{
    public function explain(ValidationResult $validation, SchedulingInput $input): array
    {
        $messages = [];

        foreach ($validation->violations() as $violation) {
            $messages[] = [
                'severity' => $violation->severity,
                'rule' => $violation->ruleId,
                'message' => $this->humanize($violation->message, $violation->context, $input),
                'penalty' => $violation->penalty,
            ];
        }

        return $messages;
    }

    private function humanize(string $message, array $context, SchedulingInput $input): string
    {
        if (isset($context['teacher_id'])) {
            $teacher = $input->teacher((string) $context['teacher_id']);
            $message .= ' Docent: ' . ($teacher?->name ?? $context['teacher_id']) . '.';
        }

        if (isset($context['class_group_id'])) {
            $classGroup = $input->classGroup((string) $context['class_group_id']);
            $message .= ' Klas: ' . ($classGroup?->name ?? $context['class_group_id']) . '.';
        }

        if (isset($context['room_id'])) {
            $room = $input->room((string) $context['room_id']);
            $message .= ' Lokaal: ' . ($room?->name ?? $context['room_id']) . '.';
        }

        if (isset($context['slot_id'])) {
            $slot = $input->slot((string) $context['slot_id']);
            $message .= ' Moment: ' . ($slot?->label ?? $context['slot_id']) . '.';
        }

        return $message;
    }
}


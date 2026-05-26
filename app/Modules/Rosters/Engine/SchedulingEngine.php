<?php

declare(strict_types=1);

namespace Roostar\Modules\Rosters\Engine;

use Roostar\Modules\Rosters\Engine\Contracts\Optimizer;
use Roostar\Modules\Rosters\Engine\Explanation\ScheduleExplainer;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\Scoring\ScheduleScorer;
use Roostar\Modules\Rosters\Engine\Scheduling\InitialScheduler;

final class SchedulingEngine
{
    /**
     * @param Optimizer[] $optimizers
     */
    public function __construct(
        private readonly InitialScheduler $initialScheduler,
        private readonly ScheduleScorer $scorer,
        private readonly ScheduleExplainer $explainer,
        private readonly array $optimizers,
    ) {
    }

    public function run(SchedulingInput $input): SchedulingRunResult
    {
        $schedule = $this->initialScheduler->createInitialSchedule($input);
        $score = $this->scorer->score($schedule, $input);
        $steps = [[
            'name' => 'Basisrooster maken',
            'score' => $score->value,
            'valid' => $score->validation->isValid(),
            'hard_count' => $score->validation->hardCount(),
            'soft_count' => $score->validation->softCount(),
        ]];

        foreach ($this->optimizers as $optimizer) {
            $before = $score;
            $candidate = $optimizer->optimize($schedule, $input, $this->scorer);
            $candidateScore = $this->scorer->score($candidate, $input);

            if ($candidateScore->isBetterThan($score)) {
                $schedule = $candidate;
                $score = $candidateScore;
            }

            $steps[] = [
                'name' => $optimizer->name(),
                'score' => $score->value,
                'valid' => $score->validation->isValid(),
                'hard_count' => $score->validation->hardCount(),
                'soft_count' => $score->validation->softCount(),
                'improved' => $score->value > $before->value,
            ];
        }

        return new SchedulingRunResult(
            $schedule,
            $score,
            $steps,
            $this->explainer->explain($score->validation, $input),
        );
    }
}


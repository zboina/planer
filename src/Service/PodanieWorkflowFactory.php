<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Service;

use Planer\PlanerBundle\Entity\PodanieUrlopowe;
use Planer\PlanerBundle\Entity\WorkflowKrok;
use Planer\PlanerBundle\Repository\PodanieUrlopoweRepository;
use Planer\PlanerBundle\Repository\WorkflowKrokRepository;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PodanieWorkflowFactory
{
    public const WORKFLOW_NAME = 'podanie_workflow';

    private ?StateMachine $workflow = null;

    /** @var WorkflowKrok[]|null */
    private ?array $steps = null;

    public function __construct(
        private readonly WorkflowKrokRepository $krokRepo,
        private readonly PodanieUrlopoweRepository $podanieRepo,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function getWorkflow(): StateMachine
    {
        if ($this->workflow !== null) {
            return $this->workflow;
        }

        $steps = $this->getActiveSteps();

        $places = ['zlozony'];
        $transitions = [];

        $prevPlace = 'zlozony';
        $activeStates = ['zlozony']; // states that are not terminal

        foreach ($steps as $i => $step) {
            $isLast = ($i === count($steps) - 1);
            $nextPlace = $isLast ? 'zatwierdzony' : $step->getKey() . '_ok';

            if (!$isLast) {
                $places[] = $nextPlace;
                $activeStates[] = $nextPlace;
            }

            $transitions[] = new Transition(
                'akceptuj_' . $step->getKey(),
                $prevPlace,
                $nextPlace,
            );

            $transitions[] = new Transition(
                'odrzuc_' . $step->getKey(),
                $prevPlace,
                'odrzucony',
            );

            $prevPlace = $nextPlace;
        }

        $places[] = 'zatwierdzony';
        $places[] = 'odrzucony';
        $places[] = 'anulowany';

        // Add orphan statuses from existing podania that are not in current workflow definition.
        // This prevents crashes when workflow steps have been changed after podania were created.
        try {
            $existingStatuses = $this->podanieRepo->findDistinctStatuses();
            foreach ($existingStatuses as $status) {
                if (!in_array($status, $places, true)) {
                    $places[] = $status;
                }
            }
        } catch (\Exception) {
            // Table may not exist yet
        }

        // anuluj from all non-terminal states + terminal (zatwierdzony, odrzucony, orphans)
        $anulujFrom = [];
        foreach ($places as $place) {
            if ($place !== 'anulowany') {
                $anulujFrom[] = $place;
            }
        }
        $transitions[] = new Transition('anuluj', $anulujFrom, 'anulowany');

        $definition = new Definition(
            $places,
            $transitions,
            ['zlozony'],
        );

        $markingStore = new MethodMarkingStore(true, 'status');

        $this->workflow = new StateMachine(
            $definition,
            $markingStore,
            $this->dispatcher,
            self::WORKFLOW_NAME,
        );

        return $this->workflow;
    }

    /**
     * @return WorkflowKrok[]
     */
    public function getSteps(): array
    {
        if ($this->steps !== null) {
            return $this->steps;
        }

        try {
            $this->steps = $this->krokRepo->findAllOrdered();
        } catch (\Exception) {
            // Table may not exist yet (before migration)
            $this->steps = [];
        }

        return $this->steps;
    }

    /**
     * Steps that are active in the workflow (not pomijalne).
     * @return WorkflowKrok[]
     */
    public function getActiveSteps(): array
    {
        return array_values(array_filter(
            $this->getSteps(),
            fn(WorkflowKrok $s) => !$s->isPomijalne(),
        ));
    }

    public function getStepByKey(string $key): ?WorkflowKrok
    {
        foreach ($this->getSteps() as $step) {
            if ($step->getKey() === $key) {
                return $step;
            }
        }
        return null;
    }

    /**
     * Find the enabled akceptuj transition for a podanie.
     */
    public function findEnabledAcceptTransition(PodanieUrlopowe $podanie): ?string
    {
        try {
            $workflow = $this->getWorkflow();
            foreach ($workflow->getEnabledTransitions($podanie) as $t) {
                if (str_starts_with($t->getName(), 'akceptuj_')) {
                    return $t->getName();
                }
            }
        } catch (\Exception) {
            // Status not recognized by current workflow
        }
        return null;
    }

    /**
     * Find the enabled odrzuc transition for a podanie.
     */
    public function findEnabledRejectTransition(PodanieUrlopowe $podanie): ?string
    {
        try {
            $workflow = $this->getWorkflow();
            foreach ($workflow->getEnabledTransitions($podanie) as $t) {
                if (str_starts_with($t->getName(), 'odrzuc_')) {
                    return $t->getName();
                }
            }
        } catch (\Exception) {
            // Status not recognized by current workflow
        }
        return null;
    }

    /**
     * Check if any transition of given type can fire.
     */
    public function can(PodanieUrlopowe $podanie, string $type): bool
    {
        try {
            $workflow = $this->getWorkflow();
            foreach ($workflow->getEnabledTransitions($podanie) as $t) {
                if ($type === 'akceptuj' && str_starts_with($t->getName(), 'akceptuj_')) {
                    return true;
                }
                if ($type === 'odrzuc' && str_starts_with($t->getName(), 'odrzuc_')) {
                    return true;
                }
                if ($type === 'anuluj' && $t->getName() === 'anuluj') {
                    return true;
                }
            }
        } catch (\Exception) {
            // Status not recognized by current workflow — no transitions available
        }
        return false;
    }

    /**
     * Check if a podanie's status is an orphan (not part of current workflow chain).
     */
    public function isOrphanStatus(PodanieUrlopowe $podanie): bool
    {
        $status = $podanie->getStatus();

        // Fixed terminal statuses are never orphan
        if (in_array($status, ['zlozony', 'zatwierdzony', 'odrzucony', 'anulowany'], true)) {
            return false;
        }

        // Check if it matches a current step's intermediate status
        foreach ($this->getSteps() as $step) {
            if ($status === $step->getKey() . '_ok') {
                return false;
            }
        }

        return true;
    }

    public function reset(): void
    {
        $this->workflow = null;
        $this->steps = null;
    }
}

<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Service;

use Planer\PlanerBundle\Entity\WorkflowKrok;

class PodanieStatusProvider
{
    /** @var array<string, array{label: string, badge: string}>|null */
    private ?array $statusMap = null;

    public function __construct(
        private readonly PodanieWorkflowFactory $workflowFactory,
    ) {
    }

    public function getLabel(string $status): string
    {
        $map = $this->getMap();
        if (isset($map[$status])) {
            return $map[$status]['label'];
        }

        // Orphan status — generate a readable label
        if (str_ends_with($status, '_ok')) {
            $key = substr($status, 0, -3);
            return 'Zaakceptowany: ' . ucfirst($key) . ' (archiwalne)';
        }

        return $status;
    }

    public function getBadge(string $status): string
    {
        return $this->getMap()[$status]['badge'] ?? 'bg-secondary-lt';
    }

    /**
     * Returns the label of the next workflow step waiting for this status.
     * E.g. status 'zlozony' with first step 'szef' → "Szef działu"
     *      status 'szef_ok' with next step 'kadry' → "Kadry"
     * Returns null for terminal statuses (zatwierdzony, odrzucony, anulowany) or orphan statuses.
     */
    public function getNextStepLabel(string $status): ?string
    {
        $steps = $this->workflowFactory->getActiveSteps();
        if (empty($steps)) {
            return null;
        }

        // Terminal statuses — no next step
        if (in_array($status, ['zatwierdzony', 'odrzucony', 'anulowany'], true)) {
            return null;
        }

        // 'zlozony' → first step is the next approver
        if ($status === 'zlozony') {
            return $steps[0]->getLabel();
        }

        // Intermediate status 'xxx_ok' → find next step
        if (str_ends_with($status, '_ok')) {
            $currentKey = substr($status, 0, -3);
            $found = false;
            foreach ($steps as $step) {
                if ($found) {
                    return $step->getLabel();
                }
                if ($step->getKey() === $currentKey) {
                    $found = true;
                }
            }
            // If was the last step, next is "Zatwierdzenie"
            if ($found) {
                return null; // last step leads to zatwierdzony, no "next step" to show
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, badge: string}>
     */
    public function getMap(): array
    {
        if ($this->statusMap !== null) {
            return $this->statusMap;
        }

        $steps = $this->workflowFactory->getActiveSteps();

        // Fixed statuses — always present
        $this->statusMap = [
            'zlozony' => [
                'label' => 'Złożony',
                'badge' => 'bg-yellow-lt',
            ],
            'zatwierdzony' => [
                'label' => 'Zatwierdzony',
                'badge' => 'bg-green-lt',
            ],
            'odrzucony' => [
                'label' => 'Odrzucony',
                'badge' => 'bg-red-lt',
            ],
            'anulowany' => [
                'label' => 'Anulowany',
                'badge' => 'bg-secondary-lt',
            ],
        ];

        // Dynamic intermediate statuses from workflow steps
        $badgeColors = ['bg-blue-lt', 'bg-purple-lt', 'bg-cyan-lt', 'bg-indigo-lt', 'bg-teal-lt'];

        foreach ($steps as $i => $step) {
            $key = $step->getKey() . '_ok';
            $color = $badgeColors[$i % count($badgeColors)];

            $this->statusMap[$key] = [
                'label' => 'Zaakceptowany: ' . $step->getLabel(),
                'badge' => $color,
            ];
        }

        return $this->statusMap;
    }

    /**
     * Returns all statuses that represent "active" (non-terminal, non-rejected) podanie.
     * Used for grafik display — green bar only for these.
     * @return string[]
     */
    public function getActiveStatuses(): array
    {
        $active = ['zlozony'];
        foreach ($this->workflowFactory->getActiveSteps() as $i => $step) {
            $isLast = ($i === count($this->workflowFactory->getActiveSteps()) - 1);
            if (!$isLast) {
                $active[] = $step->getKey() . '_ok';
            }
        }
        $active[] = 'zatwierdzony';
        return $active;
    }
}

<?php

declare(strict_types=1);

namespace Planer\PlanerBundle\Security;

use Planer\PlanerBundle\Repository\PlanerRolaRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Votes on ROLE_PLANER_* attributes by checking the planer_user_rola table.
 * Example: is_granted('ROLE_PLANER_KADRY') checks if user has rola='kadry'.
 */
class PlanerRoleVoter implements VoterInterface
{
    private const PREFIX = 'ROLE_PLANER_';

    public function __construct(
        private readonly PlanerRolaRepository $rolaRepo,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        $vote = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (!is_string($attribute) || !str_starts_with($attribute, self::PREFIX)) {
                continue;
            }

            $vote = self::ACCESS_DENIED;
            $user = $token->getUser();

            if ($user === null) {
                continue;
            }

            $rolaKey = strtolower(substr($attribute, strlen(self::PREFIX)));

            if ($this->rolaRepo->hasRole($user, $rolaKey)) {
                return self::ACCESS_GRANTED;
            }
        }

        return $vote;
    }
}

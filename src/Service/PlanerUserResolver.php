<?php

namespace Planer\PlanerBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Planer\PlanerBundle\Entity\Departament;
use Planer\PlanerBundle\Entity\PlanerUserProfile;
use Planer\PlanerBundle\Entity\UserDepartament;

/**
 * Resolves planer-related user data without requiring changes to the User entity.
 */
class PlanerUserResolver
{
    /** @var array<int, PlanerUserProfile> */
    private array $profileCache = [];

    /**
     * @param string[] $fullNameFields
     */
    public function __construct(
        private EntityManagerInterface $em,
        private array $fullNameFields = ['firstName', 'lastName'],
    ) {
    }

    /**
     * Resolve user's full name from configured fields.
     */
    public function getFullName(object $user): string
    {
        // Try dedicated getFullName() method first
        if (method_exists($user, 'getFullName') && $user->getFullName()) {
            return $user->getFullName();
        }

        // Build from configured fields
        $parts = [];
        foreach ($this->fullNameFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($user, $getter)) {
                $val = $user->$getter();
                if ($val) {
                    $parts[] = $val;
                }
            } elseif (property_exists($user, $field)) {
                $ref = new \ReflectionProperty($user, $field);
                $val = $ref->getValue($user);
                if ($val) {
                    $parts[] = $val;
                }
            }
        }

        if ($parts) {
            return implode(' ', $parts);
        }

        // Fallback to Symfony user identifier
        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }

        return '?';
    }

    /**
     * Get or create the PlanerUserProfile for a user.
     */
    public function getProfile(object|int $user): PlanerUserProfile
    {
        $userId = is_int($user) ? $user : $user->getId();

        if (isset($this->profileCache[$userId])) {
            return $this->profileCache[$userId];
        }

        $profile = $this->em->getRepository(PlanerUserProfile::class)
            ->findOneBy(['userId' => $userId]);

        if (!$profile) {
            $profile = new PlanerUserProfile($userId);
            $this->em->persist($profile);
            $this->em->flush();
        }

        $this->profileCache[$userId] = $profile;
        return $profile;
    }

    public function getAdres(object|int $user): ?string
    {
        // If user entity has getAdres(), use it directly
        if (is_object($user) && method_exists($user, 'getAdres')) {
            return $user->getAdres();
        }

        return $this->getProfile($user)->getAdres();
    }

    public function setAdres(object|int $user, ?string $adres): void
    {
        if (is_object($user) && method_exists($user, 'setAdres')) {
            $user->setAdres($adres);
            return;
        }

        $this->getProfile($user)->setAdres($adres);
    }

    public function getIloscDniUrlopu(object|int $user): int
    {
        if (is_object($user) && method_exists($user, 'getIloscDniUrlopuWRoku')) {
            return $user->getIloscDniUrlopuWRoku();
        }

        return $this->getProfile($user)->getIloscDniUrlopuWRoku();
    }

    public function setIloscDniUrlopu(object|int $user, int $value): void
    {
        if (is_object($user) && method_exists($user, 'setIloscDniUrlopuWRoku')) {
            $user->setIloscDniUrlopuWRoku($value);
            return;
        }

        $this->getProfile($user)->setIloscDniUrlopuWRoku($value);
    }

    public function getGlownyDepartament(object $user): ?Departament
    {
        if (method_exists($user, 'getGlownyDepartament')) {
            return $user->getGlownyDepartament();
        }

        $ud = $this->em->getRepository(UserDepartament::class)
            ->findOneBy(['user' => $user->getId(), 'czyGlowny' => true]);

        return $ud?->getDepartament();
    }

    /**
     * @return Departament[]
     */
    public function getDepartamentList(object $user): array
    {
        if (method_exists($user, 'getDepartamentList')) {
            return $user->getDepartamentList();
        }

        $uds = $this->em->getRepository(UserDepartament::class)
            ->findBy(['user' => $user->getId()]);

        return array_map(fn(UserDepartament $ud) => $ud->getDepartament(), $uds);
    }
}

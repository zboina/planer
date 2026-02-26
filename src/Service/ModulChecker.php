<?php

namespace Planer\PlanerBundle\Service;

use Planer\PlanerBundle\Repository\PlanerModulRepository;
use Planer\PlanerBundle\Repository\UserDepartamentRepository;
use Symfony\Bundle\SecurityBundle\Security;

class ModulChecker
{
    /** @var array<string, bool> */
    private array $modulCache = [];

    /** @var array<int, int[]> */
    private array $userDeptCache = [];

    public function __construct(
        private readonly PlanerModulRepository $modulRepo,
        private readonly UserDepartamentRepository $udRepo,
        private readonly Security $security,
    ) {
    }

    public function hasAccess(string $kod): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }

        $cacheKey = $kod . '_' . $user->getUserIdentifier();
        if (isset($this->modulCache[$cacheKey])) {
            return $this->modulCache[$cacheKey];
        }

        $modul = $this->modulRepo->findByKod($kod);
        if (!$modul || !$modul->isAktywny()) {
            $this->modulCache[$cacheKey] = false;
            return false;
        }

        $result = match ($modul->getTrybDostepu()) {
            'wszyscy' => true,
            'role' => $this->checkRole($modul->getDozwoloneRole()),
            'and' => $this->checkRole($modul->getDozwoloneRole())
                && $this->checkDepartament($user, $modul->getDozwoloneDepartamentyIds()),
            'or' => $this->checkRole($modul->getDozwoloneRole())
                || $this->checkUser($user, $modul->getDozwoleniUserIds())
                || $this->checkDepartament($user, $modul->getDozwoloneDepartamentyIds()),
            'uzytkownicy' => $this->checkUser($user, $modul->getDozwoleniUserIds()),
            default => false,
        };

        $this->modulCache[$cacheKey] = $result;
        return $result;
    }

    private function checkRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->security->isGranted($role)) {
                return true;
            }
        }
        return false;
    }

    private function checkUser(object $user, array $userIds): bool
    {
        if (!method_exists($user, 'getId')) {
            return false;
        }
        return in_array($user->getId(), $userIds, false);
    }

    private function checkDepartament(object $user, array $departamentIds): bool
    {
        if (!$departamentIds || !method_exists($user, 'getId')) {
            return false;
        }

        $userId = $user->getId();

        if (!isset($this->userDeptCache[$userId])) {
            $uds = $this->udRepo->findBy(['user' => $user]);
            $this->userDeptCache[$userId] = array_map(
                fn($ud) => $ud->getDepartament()->getId(),
                $uds
            );
        }

        return !empty(array_intersect($this->userDeptCache[$userId], $departamentIds));
    }
}

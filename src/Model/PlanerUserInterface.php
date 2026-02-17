<?php

namespace Planer\PlanerBundle\Model;

/**
 * Marker interface for Doctrine resolve_target_entities.
 *
 * Your User class can optionally implement this interface.
 * If it does, Doctrine relations will work natively.
 * If it doesn't, the bundle will still work — it uses PlanerUserResolver
 * to access user data and PlanerUserProfile to store planer-specific fields.
 *
 * For full integration, add to your User class:
 *   implements PlanerUserInterface
 *   use PlanerUserTrait;
 */
interface PlanerUserInterface
{
    public function getId(): ?int;
}

<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use customiesdevs\customies\item\ItemComponents;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\ProjectileItem;
use pocketmine\player\Player;
use Unknown\CustomItems\entity\CustomProjectile;

/** Item que é arremessado ao usar, como bola de neve ou ovo. */
final class CustomThrowable extends ProjectileItem implements ItemComponents {
	use CustomItemTrait;

	public function getThrowForce(): float {
		return $this->definition->getProjectile()["force"];
	}

	protected function createEntity(Location $location, Player $thrower): Throwable {
		return new CustomProjectile($location, $thrower, $this->definition->getProjectile(), (clone $this)->setCount(1));
	}
}

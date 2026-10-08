<?php

declare(strict_types=1);

namespace Unknown\CustomItems\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use Unknown\CustomItems\item\Effects;

/**
 * Efeitos e fogo que um projétil customizado aplica em quem ele acerta.
 *
 * @phpstan-import-type EffectData from \Unknown\CustomItems\item\ItemDefinition
 */
trait HitExtrasTrait {

	/**
	 * Chame só depois de conferir {@link damaged()}.
	 *
	 * @param list<EffectData> $effects
	 */
	protected function applyHitExtras(Entity $entityHit, array $effects, int $fireSeconds): void {
		if(!$entityHit instanceof Living || !$entityHit->isAlive()) {
			return;
		}

		Effects::apply($entityHit, $effects);

		if($fireSeconds > 0) {
			$ev = new EntityCombustByEntityEvent($this, $entityHit, $fireSeconds);
			$ev->call();
			if(!$ev->isCancelled()) {
				$entityHit->setOnFire($ev->getDuration());
			}
		}
	}

	/**
	 * Diz se o dano deste projétil passou. Assim uma área sem PvP, um plugin que cancela o dano ou o
	 * tempo de invencibilidade após um golpe também barram os extras. Funciona porque o
	 * Entity::attack só grava a causa do dano quando o evento não é cancelado.
	 */
	protected function damaged(Entity $entityHit): bool {
		$cause = $entityHit->getLastDamageCause();
		return match (true) {
			$cause instanceof EntityDamageByChildEntityEvent => $cause->getChild() === $this,
			$cause instanceof EntityDamageByEntityEvent => $cause->getDamager() === $this,
			default => false,
		};
	}
}

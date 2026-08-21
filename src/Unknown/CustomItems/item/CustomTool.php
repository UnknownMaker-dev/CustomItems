<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\item\Tool;
use Unknown\CustomItems\libs\customiesdevs\customies\item\ItemComponents;

/** Ferramenta ou arma customizada: tem durabilidade, dano e tipo/nível de ferramenta. */
final class CustomTool extends Tool implements ItemComponents {
	use CustomItemTrait;

	public function getMaxDurability(): int {
		return $this->definition->getMaxDurability();
	}

	public function getBlockToolType(): int {
		return $this->definition->getToolType();
	}

	public function getBlockToolHarvestLevel(): int {
		return $this->definition->getHarvestLevel();
	}

	protected function getBaseMiningEfficiency(): float {
		return $this->definition->getEfficiency();
	}
}

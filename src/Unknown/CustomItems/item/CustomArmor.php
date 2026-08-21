<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\item\Armor;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use Unknown\CustomItems\libs\customiesdevs\customies\item\ItemComponents;

/**
 * Peça de armadura customizada.
 *
 * O construtor é próprio porque o Armor do PocketMine pede um ArmorTypeInfo, que carrega proteção,
 * durabilidade, slot e tenacidade de uma vez — por isso ele substitui o do CustomItemTrait.
 */
final class CustomArmor extends Armor implements ItemComponents {
	use CustomItemTrait;

	public function __construct(ItemDefinition $definition) {
		$this->definition = $definition;
		parent::__construct(
			new ItemIdentifier(ItemTypeIds::newId()),
			$definition->getDisplayName(),
			$definition->createArmorTypeInfo(),
			$definition->getEnchantmentTags()
		);
		$this->initCustomComponents();
	}
}

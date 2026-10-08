<?php

declare(strict_types=1);

namespace Unknown\CustomItems\item;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Living;
use function lcg_value;

/**
 * Transforma as listas de efeitos lidas pelo {@link ItemDefinition} em efeitos de verdade.
 *
 * @phpstan-import-type EffectData from ItemDefinition
 */
final class Effects {

	private function __construct() { }

	/**
	 * Sorteia a chance de cada efeito e devolve só os que saíram.
	 *
	 * @param list<EffectData> $effects
	 * @return EffectInstance[]
	 */
	public static function roll(array $effects): array {
		$result = [];
		foreach($effects as $data){
			if($data["chance"] < 1.0 && lcg_value() >= $data["chance"]) {
				continue;
			}
			// O nome já foi validado no boot, então o parse aqui não falha.
			$effect = StringToEffectParser::getInstance()->parse($data["effect"]);
			if($effect !== null) {
				$result[] = new EffectInstance($effect, $data["duration"], $data["amplifier"], $data["particles"]);
			}
		}
		return $result;
	}

	/** @param list<EffectData> $effects */
	public static function apply(Living $target, array $effects): void {
		foreach(self::roll($effects) as $instance){
			$target->getEffects()->add($instance);
		}
	}
}

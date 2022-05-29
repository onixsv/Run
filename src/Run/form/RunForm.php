<?php
declare(strict_types=1);

namespace Run\form;

use pocketmine\form\Form;
use pocketmine\player\Player;
use Run\Run;
use Run\RunPlugin;

class RunForm implements Form{

	public function jsonSerialize() : array{
		$run = RunPlugin::getInstance()->getRun();
		return [
			"type" => "modal",
			"title" => "§lRun - Master",
			"content" => "입장이 " . ($run instanceof Run && !$run->isRunning() ? "§a가능" : "§c불가능") . "§f합니다." . ($run instanceof Run ? "\n플레이어 목록: " . implode(", ", $run->getPlayers()) : ""),
			"button1" => "§l입장하기",
			"button2" => "§l나가기"
		];
	}

	public function handleResponse(Player $player, $data) : void{
		$run = RunPlugin::getInstance()->getRun();
		if($data){
			if($run instanceof Run){
				if(!$run->isRunning()){
					$run->addPlayer($player);
				}
			}
		}
	}
}
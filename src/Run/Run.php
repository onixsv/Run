<?php
declare(strict_types=1);

namespace Run;

use alvin0319\LevelAPI\LevelAPI;
use onebone\economyapi\EconomyAPI;
use OnixUtils\OnixUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use function ceil;

class Run{

	protected string $name;

	protected int $spawnX;

	protected int $spawnY;

	protected int $spawnZ;

	protected int $endX;

	protected int $endY;

	protected int $endZ;

	protected string $world;

	protected array $players = [];

	protected int $gameTime = 90;

	protected int $waitTick = 30;

	protected int $prepareTime = 10;

	protected bool $running = false;

	protected bool $teleported = false;

	public function __construct(){
		$this->prepare();
	}

	public function prepare() : void{
		$randomData = RunPlugin::getInstance()->randomMap();

		$name = $randomData["name"];
		$spawnX = $randomData["spawnX"];
		$spawnY = $randomData["spawnY"];
		$spawnZ = $randomData["spawnZ"];
		$endX = $randomData["endX"];
		$endY = $randomData["endY"];
		$endZ = $randomData["endZ"];
		$world = $randomData["world"];

		$this->name = $name;
		$this->spawnX = $spawnX;
		$this->spawnY = $spawnY;
		$this->spawnZ = $spawnZ;
		$this->endX = $endX;
		$this->endY = $endY;
		$this->endZ = $endZ;
		$this->world = $world;

		$this->gameTime = 90;
		$this->waitTick = 30;
		$this->prepareTime = 10;
		$this->running = false;
		$this->teleported = false;
		$this->players = [];
	}

	public function tick() : void{
		if(!$this->running){
			if(count($this->players) >= 2){
				if($this->waitTick > 0){
					--$this->waitTick;
					$this->broadcast("게임 준비까지 §d" . $this->waitTick . "§f초 남았습니다.", 0);
				}else{
					if(!$this->teleported){
						$this->broadcast("게임 준비중입니다.", 0);
						foreach($this->players as $name){
							$player = Server::getInstance()->getPlayerExact($name);
							if($player instanceof Player){
								$player->setImmobile(true);
								$player->teleport(new Position($this->spawnX, $this->spawnY + 1, $this->spawnZ, Server::getInstance()->getWorldManager()->getWorldByName($this->world)));
							}else{
								unset($this->players[array_search($name, $this->players)]);
							}
						}
						$this->teleported = true;
					}
				}

				if($this->waitTick === 0 && $this->prepareTime > 0){
					--$this->prepareTime;
					$this->broadcast("게임 시작까지 §d" . $this->prepareTime . "§f초 남았습니다.", 0);
				}elseif($this->waitTick === 0 && $this->prepareTime === 0){
					$this->running = true;
					$this->broadcast("게임이 시작되었습니다!", 2);
					$this->broadcast("부정 방지를 위해 모든 이펙트가 클리어 되었습니다.", 2);
					foreach($this->players as $name){
						$player = Server::getInstance()->getPlayerExact($name);
						if($player instanceof Player){
							$player->setImmobile(false);
							$player->teleport(new Position($this->spawnX, $this->spawnY + 1, $this->spawnZ, Server::getInstance()->getWorldManager()->getWorldByName($this->world)));
							$player->getEffects()->clear();
							$player->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), 99999, 1));
						}else{
							unset($this->players[array_search($name, $this->players)]);
						}
					}
				}
			}
		}else{
			--$this->gameTime;

			if($this->gameTime < 0){
				foreach($this->players as $name){
					$player = Server::getInstance()->getPlayerExact($name);
					if($player instanceof Player){
						$player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
						OnixUtils::message($player, "게임이 무승부로 끝났습니다!");
					}
				}
				$this->prepare();
			}
		}
	}

	public function end(Player $winner){
		foreach($this->players as $name){
			$player = Server::getInstance()->getPlayerExact($name);
			if($player instanceof Player){
				$player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
				if($winner->getName() !== $player->getName()){
					EconomyAPI::getInstance()->addMoney($player, 1000);
					OnixUtils::message($player, "참가 보상으로 1000원을 얻었습니다.");
					$lv = LevelAPI::getInstance()->getLevel($player);

					$exp = $lv * 0.1;

					LevelAPI::getInstance()->addExp($player, (int) ceil($exp));
				}
			}
		}

		EconomyAPI::getInstance()->addMoney($winner, 10000);

		OnixUtils::broadcast("달리기 게임에서 §d" . $winner->getName() . "§f님이 우승했습니다!");
		OnixUtils::broadcast("맵: " . $this->name);
		$this->prepare();
		$lv = LevelAPI::getInstance()->getLevel($winner);

		$exp = $lv * 0.3;

		LevelAPI::getInstance()->addExp($winner, (int) ceil($exp));
	}

	public function broadcast(string $message, int $type){
		switch($type){
			case 0:
				foreach($this->players as $name){
					$player = Server::getInstance()->getPlayerExact($name);
					if($player instanceof Player){
						$player->sendTip($message);
					}else{
						unset($this->players[array_search($name, $this->players)]);
					}
				}
				break;
			case 1:
				foreach($this->players as $name){
					$player = Server::getInstance()->getPlayerExact($name);
					if($player instanceof Player){
						$player->sendTitle($message);
					}else{
						unset($this->players[array_search($name, $this->players)]);
					}
				}
				break;
			case 2:
				foreach($this->players as $name){
					$player = Server::getInstance()->getPlayerExact($name);
					if($player instanceof Player){
						OnixUtils::message($player, $message);
					}else{
						unset($this->players[array_search($name, $this->players)]);
					}
				}
				break;
		}
	}

	public function addPlayer(Player $player) : void{
		$this->players[] = $player->getName();
		OnixUtils::message($player, "이번 맵은 §d" . $this->name . " §f입니다!");
		$this->broadcast("§d" . $player->getName() . "§f님이 달리기 게임에 참여했습니다.", 2);
		$player->teleport(new Position($this->spawnX, $this->spawnY + 1, $this->spawnZ, Server::getInstance()->getWorldManager()->getWorldByName($this->world)));
	}

	public function removePlayer(Player $player) : void{
		unset($this->players[array_search($player->getName(), $this->players)]);
		$this->broadcast("§d" . $player->getName() . "§f님이 달리기 게임에서 퇴장하셨습니다.", 2);

		if(count($this->players) === 0){
			$this->prepare();
		}
	}

	public function isPlayer(Player $player) : bool{
		return in_array($player->getName(), $this->players);
	}

	public function canCancelMove() : bool{
		return !$this->running && $this->waitTick === 0 && $this->prepareTime > 0;
	}

	public function isRunning() : bool{
		return $this->running && $this->waitTick === 0 && $this->prepareTime === 0;
	}

	public function getEnd() : Position{
		return new Position($this->endX, $this->endY, $this->endZ, Server::getInstance()->getWorldManager()->getWorldByName($this->world));
	}

	public function getPlayers() : array{
		return $this->players;
	}
}
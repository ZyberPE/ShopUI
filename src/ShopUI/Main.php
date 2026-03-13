<?php

namespace ShopUI;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\StringToItemParser;

use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase{

    public function onEnable(): void{
        $this->saveDefaultConfig();
    }

    public function getMessage(string $key, array $replace = []): string{

        $msg = $this->getConfig()->get("messages")[$key] ?? "";

        $prefix = $this->getConfig()->get("messages")["prefix"];
        $msg = str_replace("{prefix}", $prefix, $msg);

        foreach($replace as $k => $v){
            $msg = str_replace("{" . $k . "}", $v, $msg);
        }

        return $msg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            $sender->sendMessage($this->getMessage("player-only"));
            return true;
        }

        if($command->getName() === "shopui"){
            $this->openShop($sender);
        }

        return true;
    }

    public function openShop(Player $player): void{

        $sections = $this->getConfig()->get("sections");

        $form = new SimpleForm(function(Player $player, $data) use ($sections){

            if($data === null){
                return;
            }

            $keys = array_keys($sections);
            $section = $keys[$data];

            $this->openCategory($player, $section);
        });

        $form->setTitle($this->getConfig()->get("title"));

        foreach($sections as $section){
            $form->addButton($section["name"]);
        }

        $player->sendForm($form);
    }

    public function openCategory(Player $player, string $section): void{

        $items = $this->getConfig()->get("sections")[$section]["items"];

        $form = new SimpleForm(function(Player $player, $data) use ($items){

            if($data === null){
                return;
            }

            $keys = array_keys($items);
            $itemKey = $keys[$data];
            $itemData = $items[$itemKey];

            $price = $itemData["price"];
            $amount = $itemData["amount"];
            $name = $itemData["name"];

            $eco = EconomyAPI::getInstance();
            $money = $eco->myMoney($player);

            if($money < $price){
                $player->sendMessage($this->getMessage("not-enough-money"));
                return;
            }

            $item = StringToItemParser::getInstance()->parse($itemData["id"]);
            $item->setCount($amount);

            if(!$player->getInventory()->canAddItem($item)){
                $player->sendMessage($this->getMessage("inventory-full"));
                return;
            }

            $eco->reduceMoney($player, $price);
            $player->getInventory()->addItem($item);

            $player->sendMessage($this->getMessage("purchased", [
                "amount" => $amount,
                "item" => $name,
                "price" => $price
            ]));

        });

        $form->setTitle("Shop");

        foreach($items as $data){

            $name = $data["name"];
            $price = $data["price"];
            $amount = $data["amount"];

            $form->addButton("$name\n§a$$price §7x$amount");
        }

        $player->sendForm($form);
    }
}

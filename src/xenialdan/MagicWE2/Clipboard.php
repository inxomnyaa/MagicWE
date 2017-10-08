<?php

declare(strict_types=1);

namespace xenialdan\MagicWE2;


use pocketmine\level\Position;
use pocketmine\math\Vector3;


class Clipboard{

	private $data;
	/** @var Position */
	private $offset;

	public function __construct($data = null){
		$this->setData($data);
	}

	public function getData(){
		return $this->data;
	}

	public function setData($data){
		$this->data = $data;
	}

	public function setOffset(Vector3 $offset){
		$this->offset = $offset;
	}

	public function getOffset(){
		return $this->offset;
	}

	//Serialize, deserialize to/from file
}
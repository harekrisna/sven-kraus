<?php

namespace App\Model;
use Nette;
use Tracy\Debugger;

class News extends TableExtended {
    /** @var string */
	protected $tableName = 'news';
}
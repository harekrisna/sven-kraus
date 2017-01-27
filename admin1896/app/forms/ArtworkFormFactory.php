<?php

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class ArtworkFormFactory extends Nette\Object {
	/** @var FormFactory */
	private $factory;
	/** @var Artwork */
	private $artwork;

	private $record;
		
	public function __construct(FormFactory $factory, \App\Model\Artwork $artwork) {
		$this->factory = $factory;
		$this->artwork = $artwork;
	}

	public function create($record = null) {
		$this->record = $record;

		$form = $this->factory->create();
		$data = $form->addContainer('data');

		$data->addText('title', 'Název')
			 ->setRequired('Zadej název.');

		$data->addText('description_list', 'Popis v přehledu');
		$data->addTextArea('description_detail', 'Popis v detailu');

	    $form->addSubmit('add', 'Přidat artwork');
	    $form->addSubmit('edit', 'Uložit změny');

	    if($record != null) {
	    	$form['data']->setDefaults($record);
	    }

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}

	public function formSucceeded(Form $form, $values) {
		try {
			if($form->isSubmitted()->name == "add") {
				$values->data->position = $this->artwork->findAll()->max('position') + 1;
				$new_record = $this->artwork->insert($values->data);
				$photos_folder = $new_record->id."_".Strings::webalize($new_record->title);
				$this->artwork->update($new_record->id, ['photos_folder' => $photos_folder]);
				$photos_folder = "./images/photos/".$photos_folder;
				mkdir($photos_folder);
				chmod($photos_folder, 0777);
				mkdir($photos_folder."/photos");
				chmod($photos_folder, 0777);
				mkdir($photos_folder."/previews");
				chmod($photos_folder, 0777);
			}
			else {
				$this->artwork->update($this->record->id, $values->data);
			}
		}
		catch(\App\Model\DuplicateException $e) {
			if($e->foreign_key == "title") {
				$form['data']['title']->addError("Artwork s tímto názvem již existuje.");
			}
		}
	}
}

<?php

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use Nette\Utils\FileSystem;
use Tracy\Debugger;

class NewFormFactory extends Nette\Object {
	/** @var FormFactory */
	private $factory;
	/** @var News */
	private $model;

	private $record;
		
	public function __construct(FormFactory $factory, \App\Model\News $new) {
		$this->factory = $factory;
		$this->model = $new;
	}

	public function create($record = null) {
		$this->record = $record;

		$form = $this->factory->create();
		$data = $form->addContainer('data');

		$data->addText('title', 'Název')
			 ->setRequired('Zadej název.');

		$data->addDatePicker('date_from', 'Datum od');
		$data->addDatePicker('date_to', 'Datum do');

		$data->addText('subtitle', 'Podtitul');

		$data->addTextArea('description', 'Popis v detailu');
		
		$form->addUpload('photo_file', 'Obrázek:')
    		 ->setRequired(FALSE)
    		 ->addRule(Form::IMAGE, 'Obrázek musí být JPEG, PNG nebo GIF.');

	    $form->addSubmit('add', 'Přidat novinku');
	    $form->addSubmit('edit', 'Uložit změny');

	    if($record != null) {
	    	$form['data']->setDefaults($record);
	    }

		$form->onSuccess[] = array($this, 'formSucceeded');
		return $form;
	}

	public function formSucceeded(Form $form, $values) {
		if($form->isSubmitted()->name == "add") {
			$new_record = $this->model->insert($values->data);
			
			if($values->photo_file->isOk() && $values->photo_file->isImage()) {
				$photo_filename = $new_record->id."_".$values->photo_file->getSanitizedName();
				$image = $values->photo_file->toImage();
				$image->save("./images/news/".$photo_filename);
				chmod("./images/news/".$photo_filename, 0777);

				$image->resize(NULL, 240);
				$image->save("./images/news/previews/".$photo_filename);
				chmod("./images/news/previews/".$photo_filename, 0777);
				
				$this->model->update($new_record->id, ['photo_file' => $photo_filename]);
			}
		}
		else {
			if($values->photo_file->isOk() && $values->photo_file->isImage()) {
				if($this->record->photo_file != null) {
					FileSystem::delete("./images/news/".$this->record->photo_file);
					FileSystem::delete("./images/news/previews/".$this->record->photo_file);
				}

				$photo_filename = $this->record->id."_".$values->photo_file->getSanitizedName();
				$image = $values->photo_file->toImage();
				$image->save("./images/news/".$photo_filename);
				chmod("./images/news/".$photo_filename, 0777);

				$image->resize(NULL, 240);
				$image->save("./images/news/previews/".$photo_filename);
				chmod("./images/news/previews/".$photo_filename, 0777);
				
				$this->model->update($this->record->id, ['photo_file' => $photo_filename]);
			}

			$this->model->update($this->record->id, $values->data);
		}
	}
}

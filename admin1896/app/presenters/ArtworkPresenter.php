<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;
use Nette\Application\UI\Form;
use App\Forms\ArtworkFormFactory;


class ArtworkPresenter extends BasePresenter {
	/** @var object */
    private $record;
	/** @var Vehicle */
	private $model;
	/** @var ArtworkFormFactory @inject */
	public $factory;

	protected function startup() {
		parent::startup();
		$this->model = $this->artwork;
	}
	
	public function renderAdd() {
		$this->setView("form");
		$this->template->form_title = "Přidat artwork";
	}

	public function renderEdit($record_id) {
		$this->setView("form");
		$this->template->form_title = "Upravit artwork";
	}

	public function actionEdit($record_id) {
		$this->record = $this->model->get($record_id);
		
		if (!$this->record)
            throw new Nette\Application\BadRequestException("Artwork nenalezen.");
			
        $this->template->record = $this->record;
	}

	public function renderList($category_id) {
        $this->template->records = $this->model->findAll();
	}

	protected function createComponentForm() {
		$form = $this->factory->create($this->record);
		
		$form->onSuccess[] = function ($form) {
			if($form->isSubmitted()->name == "add") {
				$this->flashMessage("Artwork byl přidán", 'success');
				$form->getPresenter()->redirect('Artwork:add');
			}
			else {
				$this->flashMessage("ArtworkW byl upraven", 'success');
				$form->getPresenter()->redirect('Artwork:list');
			}
		};

		return $form;
	}	

	public function actionDelete($id) {
		$record = $this->model->get($id);
		if($record->photos_folder != "") {
			\Nette\Utils\FileSystem::delete("./images/photos/".$record->photos_folder);
		}
		
		Debugger::fireLog($id);
		$this->payload->success = $this->model->delete($id);
		$this->sendPayload();
	}
}

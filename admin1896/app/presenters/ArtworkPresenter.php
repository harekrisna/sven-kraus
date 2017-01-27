<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;
use Nette\Application\UI\Form;
use App\Forms\ArtworkFormFactory;
use Nette\Database\SqlLiteral;
use Nette\Utils\Strings;
use Nette\Utils\FileSystem;


class ArtworkPresenter extends BasePresenter {
	/** @var object */
    private $record;
	/** @var Vehicle */
	private $model;
	/** @var ArtworkFormFactory @inject */
	public $factory;

	private $delete_success;

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
        $this->template->records = $this->model->findAll()
        									   ->order('position');

        if($this->isAjax()) {
        	$this->redrawControl('artworks');
        	$this->payload->success = $this->delete_success;
        }
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
 
	}	

	public function actionDelete($id) {
		$record = $this->model->get($id);
		
		$this->model->findAll()
		            ->where('position > ?', $record->position)
					->update(['position' => new SqlLiteral("position - 1")]);

		if($record->photos_folder != "") {
			FileSystem::delete("./images/photos/".$record->photos_folder);
		}
		
		$this->delete_success = $this->model->delete($id);
		$this->setView("list");
	}

	public function handleUpdatePosition($record_id, $new_position) {
        $old_position = $this->model->get($record_id)
                                    ->position;
                             
        if($old_position != $new_position) {
            $max_position = $this->model->findAll()
                                        ->max('position');
            
            $this->model->get($record_id)
            			->update(['position' => $new_position]);
            			
            $sign = $old_position < $new_position ? "-" : "+";
            $this->model->findAll()
                        ->where("id != ? AND position BETWEEN ? AND ?", $record_id, min($old_position, $new_position), max($old_position, $new_position))
                        ->update(["position" => new SqlLiteral("position {$sign} 1")]);
        }
        
        $this->redirect('list');
	}	

	public function actionGenerateToWeb() {
		$artworks = $this->model->findAll();
		$main_folder = "../../artworks/test";

		FileSystem::delete($main_folder);
		FileSystem::createDir($main_folder);

		foreach ($artworks as $artwork) {
			$position = str_pad($artwork->position, 2, "0", STR_PAD_LEFT);
			$artwork_folder = $main_folder."/".$position."_".Strings::webalize($artwork->title);
			FileSystem::createDir($artwork_folder);

			foreach ($artwork->related('photo') as $photo) {
				$photo_file = "./images/photos/".$artwork->photos_folder."/photos/".$photo->file;
				FileSystem::copy($photo_file, $artwork_folder."/".$photo->file);
			}
		}

		//$this->redirect('list');
	}
}

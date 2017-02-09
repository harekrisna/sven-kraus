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
use Latte;


class ArtworkPresenter extends BasePresenter {
	/** @var object */
    private $record;
	/** @var Artwork */
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
				$this->flashMessage("Artwork byl upraven", 'success');
				$form->getPresenter()->redirect('Artwork:list');
			}
		};
 		
 		return $form;
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
		$artworks = $this->model->findAll()->order('position');
		$main_folder = "../../artworks/";
		$artworks_folder = $main_folder."/product";
		$latte = new Latte\Engine;

		FileSystem::delete($artworks_folder);
		FileSystem::createDir($artworks_folder);

		$last_artwork = $this->model->findAll()
									->order('position DESC')
									->limit(1)
									->fetch();

		foreach ($artworks as $artwork) {
			$artwork_folder = $artworks_folder."/".Strings::padLeft($artwork->position, 2, '0')."_".Strings::webalize($artwork->title);
			FileSystem::createDir($artwork_folder);

			$photos = "./images/photos/".$artwork->photos_folder."/photos/";
			FileSystem::copy($photos, $artwork_folder);

			$previsous_artwork = $this->model->findBy(['position' => $artwork->position - 1])
											 ->fetch();

			$next_artwork = $this->model->findBy(['position' => $artwork->position + 1])
										->fetch();
			
			if($artwork->position == 1) {
				$previsous_artwork = $this->model->findAll()
												 ->order('position DESC')
												 ->limit(1)
												 ->fetch();
			}

			if($artwork->position == $last_artwork->position) {
				$next_artwork = $this->model->findAll()
										    ->order('position ASC')
											->limit(1)
											->fetch();
			}

			Debugger::fireLog($previsous_artwork);

			$params = array(
				'artwork' => $artwork,
				'photos' => $artwork->related('photo')->order('position'),
				'artwork_folder' => Strings::padLeft($artwork->position, 2, '0')."_".Strings::webalize($artwork->title),
				'previsous_artwork_folder' =>  Strings::padLeft($previsous_artwork->position, 2, '0')."_".Strings::webalize($previsous_artwork->title),
				'next_artwork_folder' => Strings::padLeft($next_artwork->position, 2, '0')."_".Strings::webalize($next_artwork->title),
			);
			
			$template = $latte->renderToString('../app/templates/components/generator-templates/artwork-detail.latte', $params);
			file_put_contents($artwork_folder."/index.html", $template);
		}


		$artworks_list = [];

		foreach ($artworks as $artwork) {
			$artwork_item = $artwork->toArray();
			$artwork_item['folder'] = Strings::padLeft($artwork->position, 2, '0')."_".Strings::webalize($artwork->title);
			$artwork_item['list_photo'] = $artwork->related('photo')->order('position ASC')
																	->limit(1)
																	->fetch();
			$artworks_list[] = $artwork_item;
		}

		$params = array(
			'artworks' => $artworks_list,
		);
		
		$template = $latte->renderToString('../app/templates/components/generator-templates/artwork-list.latte', $params);
		file_put_contents($main_folder."/index.html", $template);

		$this->redirect('list');
	}
}

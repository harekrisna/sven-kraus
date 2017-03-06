<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;
use Nette\Application\UI\Form;
use App\Forms\NewFormFactory;
use Nette\Database\SqlLiteral;
use Nette\Utils\Strings;
use Nette\Utils\FileSystem;
use Latte;


class NewPresenter extends BasePresenter {
	/** @var object */
    private $record;
	/** @var News */
	private $model;
	/** @var NewFormFactory @inject */
	public $factory;

	private $delete_success;

	protected function startup() {
		parent::startup();
		$this->model = $this->news;
	}
	
	public function renderAdd() {
		$this->setView("form");
		$this->template->form_title = "Přidat novinku";
	}

	public function renderEdit($record_id) {
		$this->setView("form");
		$this->template->form_title = "Upravit novinku";
	}

	public function actionEdit($record_id) {
		$this->record = $this->model->get($record_id);
		
		if (!$this->record)
            throw new Nette\Application\BadRequestException("Novinka nenalezena.");
			
        $this->template->record = $this->record;
	}

	public function renderList() {
        $this->template->records = $this->model->findAll()
        									   ->order('date_from DESC');

        if($this->isAjax()) {
        	$this->redrawControl('news');
        	$this->payload->success = $this->delete_success;
        }
	}

	protected function createComponentForm() {
		$form = $this->factory->create($this->record);
		
		$form->onSuccess[] = function ($form) {
			if($form->isSubmitted()->name == "add") {
				$this->flashMessage("Novinka byla přidána", 'success');
				$form->getPresenter()->redirect('New:add');
			}
			else {
				$this->flashMessage("Novinka byla upravena", 'success');
				$form->getPresenter()->redirect('New:list');
			}
		};
 		
 		return $form;
	}	

	public function actionDelete($id) {
		$record = $this->model->get($id);

		if($record->photo_file != "") {
			FileSystem::delete("./images/news/".$record->photo_file);
			FileSystem::delete("./images/news/previews".$record->photo_file);
		}
		
		$this->delete_success = $this->model->delete($id);
		$this->setView("list");
	}	

	public function actionGenerateToWeb() {
		$news = $this->model->findAll()
							->order('date_from DESC');
							
		$main_folder = "../../news";
		$news_folder = $main_folder."/news/";
		$latte = new Latte\Engine;

		FileSystem::delete($news_folder);
		FileSystem::createDir($news_folder);

		$news_array = [];
		$position = 0;

		foreach ($news as $new) {
			$news_array[++$position] = $new;
		}

		$position = 0;

		foreach ($news as $new) {
			$position++;
			$new_folder = $news_folder."/".Strings::webalize($new->title);
			FileSystem::createDir($new_folder);

			$new_photo = "./images/news/".$new->photo_file;
			FileSystem::copy($new_photo, $new_folder."/".$new->photo_file);
			
			if($position == 1) {
				$previous_new_folder = null;	
			}
			else {
				$previous_new_folder = "../".Strings::webalize($news_array[$position-1]->title);
			}

			if($position == count($news_array)) {
				$next_new_folder = null;
			}
			else {
				$next_new_folder = "../".Strings::webalize($news_array[$position+1]->title);	
			}

			$params = array(
				'new' => $new,
				'previous_new_folder' => $previous_new_folder,
				'next_new_folder' => $next_new_folder,
			);
			
			$template = $latte->renderToString('../app/templates/components/generator-templates/new-detail.latte', $params);
			file_put_contents($new_folder."/index.html", $template);
		}
		
		$news_list = [];

		foreach ($news as $new) {
			$new_item = $new->toArray();
			$new_item['folder'] = Strings::webalize($new->title);
			$news_list[] = $new_item;
		}

		$params = array(
			'news' => $news_list
		);
		
		$template = $latte->renderToString('../app/templates/components/generator-templates/new-list.latte', $params);
		file_put_contents($main_folder."/index.html", $template);
		
		$this->redirect('list');
	}
}

<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {
	/** @var Artwork */
	protected $artwork;
	/** @var Photo */
	protected $photo;
	/** @var User */
	protected $user;
	/** @var News */
	protected $news;	
	
	protected function startup() {
		parent::startup();
		
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }		
        
        $this->artwork = $this->context->getService('artwork');
        $this->photo = $this->context->getService('photo');
        $this->news = $this->context->getService('news');
	
		$this->user = $this->getUser()->getIdentity();

		\Forms\DatePicker\DatePicker::register();
	}

	public function flashMessage($message, $type = 'info') {
		if ($this->isAjax()) {
			$this->payload->messages[] = ['message' => $message,
										  'type' => $type];
		}
		else {
			parent::flashMessage($message, $type);
		}
	}

	public function beforeRender() {
        parent::beforeRender();
	}
}
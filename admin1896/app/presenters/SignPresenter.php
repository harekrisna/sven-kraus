<?php

namespace App\Presenters;

use Nette;
use App\Forms\SignFormFactory;


class SignPresenter extends Nette\Application\UI\Presenter
{
	/** @var SignFormFactory @inject */
	public $factory;


	/**
	 * Sign-in form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentSignInForm()
	{
		$form = $this->factory->create();
		$form->onSuccess[] = function ($form) {
			$form->getPresenter()->redirect('Artwork:list');
		};
		return $form;
	}


	public function actionOut()
	{
		$this->getUser()->logout();
		$this->redirect('in');
	}

}

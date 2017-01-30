<?php

namespace App\Presenters;
use Nette,
	App\Model;
use Tracy\Debugger;
use Nette\Application\UI\Form;
use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\Strings;
use Nette\Database\SqlLiteral;

final class PhotoPresenter extends BasePresenter {
    
    /** @var object */
    private $record;

     /** @var object */
    private $model;   	    
    
    private $photos_dir = "./images/photos";
    
    protected function startup()  {
        parent::startup();
		$this->model = $this->photo;
    
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }
    
    public function beforeRender() {
    	parent::beforeRender();
		$this->template->photos_dir = $this->photos_dir;
    }

	function renderDetail($artwork_id) {
		$artwork = $this->artwork
		   		        ->get($artwork_id);
		
		$this->template->images_dir_count = Finder::findFiles('*.jpg', '*.jpeg', '*.png', '*.gif')->in("./images/photos/{$artwork->photos_folder}/photos")->count();
		$this->template->artwork_photos_folder = $this->photos_dir."/".$artwork->photos_folder;
		
		$this->template->photos = $this->photo
									   ->findAll()
									   ->where(["artwork_id" => $artwork_id])
									   ->order("position");
								
		$this->template->artwork = $artwork;
	}

	function handleUploadFile($artwork_id) {
		$fileTypes = array('jpg', 'jpeg', 'gif', 'png'); // Allowed file extensions
   		
		$verifyToken = md5('unique_salt' . $_POST['timestamp']);
		
		if (!empty($_FILES) && $_POST['token'] == $verifyToken) {
			
			$fileParts = pathinfo($_FILES['Filedata']['name']);
			
			if (in_array(strtolower($fileParts['extension']), $fileTypes)) {
				$artwork = $this->artwork->get($artwork_id);
				$tempFile   = $_FILES['Filedata']['tmp_name'];
				$uploadDir  = $this->photos_dir."/photos";
				$targetFile = Strings::webalize($fileParts['filename']).".".$fileParts['extension'];
				$artwork_photos_folder = $this->photos_dir."/".$artwork->photos_folder;
				
				$image = Image::fromFile($tempFile);
				//$image->resize(NULL, 1200, Image::SHRINK_ONLY);
				$image->save($artwork_photos_folder."/photos/{$targetFile}");
				chmod($artwork_photos_folder."/photos/{$targetFile}", 0777);
                $filesize = filesize($artwork_photos_folder."/photos/{$targetFile}");
                
                $new_width = $image->width;
                $new_height = $image->height;
                
                $image->resize(NULL, 240);
				$image->save($artwork_photos_folder."/previews/{$targetFile}");
				chmod($artwork_photos_folder."/previews/{$targetFile}", 0777);

                unset($image);
                								
				$photo = $this->photo->findBy(['file' => $targetFile,
											   'artwork_id' => $artwork_id]);
											   
				$max_position = $this->photo->findAll()
											->where(['artwork_id' => $artwork_id])
                                            ->max('position');
				
				if($photo->count() == 0) { // fotka s tímto názvem souboru neexistuje, vloží se nakonec				
    				$new_photo = $this->photo->insert(["file" => $targetFile,
    												   'artwork_id' => $artwork_id,
    						 						   "width" => $new_width,
    												   "height" => $new_height,
    												   "position" => $max_position + 1,
                                                     ]);

                    Debugger::enable(Debugger::PRODUCTION); // háček kvůli tomu, aby se v ajaxové odpovědi neodesílal debug bar
                    $this->setView("photo-box");
                    $this->template->photo = $this->photo->get($new_photo->id);
                    $this->template->new = true;
                    $this->template->artwork_photos_folder = $artwork_photos_folder;
				}
				
				else { // fotka s tímto názvem souboru existuje, aktualizuje se    				
    				$photo->update(['width' => $new_width,
    				                'height' => $new_height,
                                  ]);
    				                                          
                    //$this->handleUpdatePosition($new_photo->id, $max_position);
                    $photo = $photo->fetch();
                    $this->payload->photo = $this->photo->get($photo->id)
                                                        ->toArray();
                    
                    $filesize = \Latte\Runtime\Filters::bytes(filesize($artwork_photos_folder."/photos/".$targetFile));
                    $this->payload->filesize = $filesize;
                    $this->payload->file_path = $artwork_photos_folder."/previews/{$targetFile}";
                    
            		$this->payload->images_dir_count = Finder::findFiles('*.jpg', '*.jpeg', '*.gif', '*.png')->in($artwork_photos_folder."/photos")
            		                                                                                         ->count();
            		$this->payload->images_db_count = $this->photo->findAll()
            													  ->where(['artwork_id' => $artwork_id])
            		                                              ->count();
                    $this->sendPayload();
                    
				}
			}
		}
	}

	function handleRemovePhoto($photo_id) {
		$photo = $this->photo->get($photo_id);
		$dir = $this->photos_dir."/".$photo->artwork->photos_folder;
		
		@unlink($dir."/photos/".$photo->file);
		@unlink($dir."/previews/".$photo->file);

		$this->photo->findAll()
		            ->where('position > ?', $photo->position)
					->where(['artwork_id' => $photo->artwork_id])
                    ->update(["position" => new SqlLiteral("position - 1")]);

		$this->photo->delete($photo_id);
		$this->payload->images_dir_count = Finder::findFiles('*.jpg', '*.jpeg', '*.gif', '*.png')->in($dir."/photos")
		                                                                                         ->count();
		$this->payload->images_db_count = $this->photo->findAll()
													  ->where(['artwork_id' => $photo->artwork_id])
		                                              ->count();
		$this->payload->success = true;
		$this->sendPayload();
		$this->terminate();
	}

	function actionSortPhotos($artwork_id) {
		$galery = $this->artwork->get($artwork_id);
		$photos = $this->photo->findAll()
							  ->where(['artwork_id' => $artwork_id])
							  ->order('CONCAT(REPEAT("0", 18 - LENGTH(file)), file)');
		
		$position = 1;
		foreach($photos as $photo) {
			$this->photo->update($photo->id, ['position' => $position]);
			$position++;
		}
				
		$this->redirect("detail", $artwork_id);
	}	
		
	function handleUpdateDescription($photo_id, $text) {
    	$this->photo->update($photo_id, ["description" => $text]);
    	$this->sendPayload();
	}	
	
	function handleUpdatePosition($artwork_id, $photo_id, $new_position) {    	
        $old_position = $this->photo->get($photo_id)
                                    ->position;
		
        if($old_position != $new_position) {
            $max_position = $this->photo->findAll()
            							->where(['artwork_id' => $artwork_id])
                                        ->max('position');
            
            $this->photo->update($photo_id, ['position' => $new_position]);
            $sign = $old_position < $new_position ? "-" : "+";
            $this->photo->findAll()
                        ->where("id != ? AND position BETWEEN ? AND ?", $photo_id, min($old_position, $new_position), max($old_position, $new_position))
						->where(['artwork_id' => $artwork_id])
                        ->update(["position" => new SqlLiteral("position {$sign} 1")]);
        }
        
        $this->sendPayload();
	}	
}

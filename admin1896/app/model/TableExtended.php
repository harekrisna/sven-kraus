<?php

namespace App\Model;
use Nette;
use Tracy\Debugger;

abstract class TableExtended extends Table  { 

    public function insert($data) {
        $columns = $this->connection->getStructure()
                                       ->getColumns($this->tableName);
        
        // ošetření DATETIME a DATE vstupů
        foreach($columns as $column) {
            if(isset($data[$column['name']])) {
                if(($column['nativetype'] == "DATETIME" || $column['nativetype'] == "DATE") && !($data[$column['name']] instanceof \DateTime)) {
                    $data[$column['name']] = NULL;
                }
                
                if($column['nativetype'] == "YEAR" && $data[$column['name']] == "") {
                    $data[$column['name']] = NULL;
                }
            }
        }

        try {
            return $this->getTable()
                        ->insert($data);

        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            $exception = new DuplicateException($e->getMessage());
            $exception->foreign_key = substr($e->getMessage(), strpos($e->getMessage(), "for key '") + 9, -1);
            throw $exception;
		}
	}
 	   
    public function update($id, $data) {
        // kontrola, zda se do některého cizího klíče nepřiřazuje prázdný řetězec, pokud ano nastaví se hodnota klíče na NULL                
        $references = $this->connection->getStructure()
                                       ->getBelongsToReference($this->tableName);
        
        foreach($references as $column => $table) {
           if(isset($data[$column]) && $data[$column] == "") {
               $data[$column] = NULL;
           }
        }

        $columns = $this->connection->getStructure()
                                       ->getColumns($this->tableName);
        
        // ošetření DATETIME a DATE vstupů
        foreach($columns as $column) {
            if(isset($data[$column['name']])) {
                if(($column['nativetype'] == "DATETIME" || $column['nativetype'] == "DATE") && !($data[$column['name']] instanceof \DateTime)) {
                    $data[$column['name']] = NULL;
                }
                
                if($column['nativetype'] == "YEAR" && $data[$column['name']] == "") {
                    $data[$column['name']] = NULL;
                }
            }
        }

        try {
            return parent::update($id, $data);

        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            $exception = new DuplicateException($e->getMessage());
            $exception->foreign_key = substr($e->getMessage(), strpos($e->getMessage(), "for key '") + 9, -1);
            throw $exception;
        }
    }

    public function delete($id) {
        try {
            return parent::delete($id);

        } catch (Nette\Database\ForeignKeyConstraintViolationException $e) {
            return false;
        }
        
    }    
}

class DuplicateException extends \Exception {
    public $foreign_key;
}
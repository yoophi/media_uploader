<?php
App::uses('AppModel', 'Model');
/**
 * Attachment Model
 *
 */
class Attachment extends AppModel {

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'filename' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'filesize' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'mimetype' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'name' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'dir' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

	function upload(&$file) {
		if (!is_file($file['tmp_name'])) {
			return false;
		}
	}

	function createFile($file) {
		$this->__log(__METHOD__, $file);
		$this->__log(dirname(substr($file->path, strlen(WWW_ROOT))));

		$name = $file->orig_name;
		$filename = $file->name;
		$filesize = $file->size;
		$mimetype = $file->type;
		$dir = dirname(substr($file->path, strlen(WWW_ROOT)));

		$this->create();
		if ($this->save(array($this->alias => compact('name', 'filename', 'filesize', 'mimetype', 'dir')))) {
			$id = $this->getLastInsertId();

			return $id;
		}

		return false;
	}

	function __log() {
		$args = func_get_args();
		if (count($args) == 1) {
			$args = array_pop($args);
		}

		CakeLog::write('rest', print_r($args, true));
	}

}

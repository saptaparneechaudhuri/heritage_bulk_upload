<?php 
namespace Drupal\heritage_bulk_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Archiver\Zip;

class ImportContentText extends FormBase {
	/**
	* {@inheritdoc}
	*/
	public function getFormId() {    
		return 'heritage_bulk_upload_import_content_text';
	}
	/**
	* {@inheritdoc}
	*/
	public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
		$text_id = $node;
		$node_info = \Drupal\node\Entity\Node::load($node);
		$description = '';
		$labels = $node_info->field_level_labels->value;
		$labels_array = explode(',', $labels);
		$label_count = count($labels_array);
		for($i=0; $i<count($labels_array); $i++){
			if($i < $label_count-1)
				$description = $description.$labels_array[$i].', ';
			else $description = $description.$labels_array[$i];
		}
		$description = $description.', Content, Language';
		$csvCount = $label_count + 2;
		$form['text'] = array(
			'#type' => 'hidden',
			'#value' => $text_id,
		);
		$form['text_info'] = array(
			'#type' => 'container',
			'#prefix' => '<div id="text-info">',
			'#suffix' => '</div>'
		);
		if(isset($text_id) && $text_id > 0){
			$validators = array(
				'file_validate_extensions' => array('csv '),
			);
			$validators_zip = array(
				'file_validate_extensions' => array('zip '),
			);
			$form['text_info']['fieldset'] = array(
				'#type' => 'fieldset',
				'#title' => $this->t('Select the Target Source'),
				'#description' => $this->t('Choose the source into which you are importing content'), 
			);
			$sources = array();
			$source_values = db_query("SELECT id, title FROM `heritage_source_info` WHERE text_id = :text_id", array('text_id' => $text_id))->fetchAll();
			foreach($source_values AS $key => $value){
				$sources[$value->id] = $value->title;
			}
			$form['text_info']['fieldset']['sources'] = array(
				'#type' => 'select',
				'#title' => $this->t('Select the Source to which content is imported'),
				'#required' => TRUE,
				'#options' => $sources,
				'#default_value' => isset($form['text_info']['fieldset']['sources']['widget']['#default_value'])?$form['text_info']['fieldset']['sources']['widget']['#default_value']:null,
				'#ajax' => array(
					'event' => 'change',
					'wrapper' => 'source-formats',
					'callback' => '::_ajax_source_callback',
				),
			);

			
			

			
			if(!empty($form_state->getTriggeringElement())) {
		        
				// Get the corresponding sourceid from the
				// Input in the Choose source field
				$sourceid = $form_state->getUserInput()['sources'];
				
			}
			if(!isset($sourceid)) $sourceid = $form['text_info']['fieldset']['sources']['widget']['#default_value'];
			//print_r($sourceid);

			$form['text_info']['fieldset']['source_formats'] = array(
				'#type' => 'container',
				'#prefix' => '<div id="source-formats">',
				'#suffix' => '</div>'
			);
			 if(isset($sourceid)){
			
			 	$format = db_query("SELECT format FROM `heritage_source_info` WHERE id = :sourceid", array(':sourceid' => $sourceid))->fetchField();
			
			 	$form['text_info']['fieldset']['format'] = array(
			 		'#type' => 'hidden',
			 		'#value' => $format,
			
			 		
				);
			}
			if(isset($format) && $format == 'text'){
				$form['text_info']['fieldset']['source_formats']['selected_langcode'] = [
					'#type' => 'language_select',
					'#title' => $this->t('Language'),
					'#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
				]; 
				$form['text_info']['fieldset']['source_formats']['file'] = array(
					'#type' => 'managed_file',
					'#title' => t('Upload the Content in CSV format'),
					'#size' => 20,
					'#description' => t('CSV file (eg: '.$description.'). Please maintain the order of the fields.'),
					'#upload_location' => 'public://file_uploads/',
					'#upload_validators' => $validators,
				);
				$form['text_info']['fieldset']['source_formats']['csv_count'] = array(
					'#type' => 'hidden',
					'#value' => $csvCount,
				);
				$form['text_info']['fieldset']['source_formats']['csv_labels'] = array(
					'#type' => 'hidden',
					'#value' => $description,
				);
			}
			else if(isset($format) && $format == 'audio'){
				$form['text_info']['fieldset']['source_formats']['audiofiles'] = array(
					'#type' => 'managed_file',
					'#title' => t('Upload zip file containing audios in mp3 format'),
					'#upload_location' => 'public://file_uploads/audio/',
					'#upload_validators' => $validators_zip,
				);
			}
		}
		$form['actions']['submit'] = array(
			'#type' => 'submit',
			'#value' => $this->t('Import Content'),
		);
		return $form;
	}
	/**
	* {@inheritdoc} Map content to the corresponding fields
	*/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$params['text'] = $form_state->getValue('text');
		$index = 0;
		$params['source_id']  = $form_state->getValue('sources');
		$params['format'] = $form_state->getValue('format');
		$uploaded_file_id = $form_state->getValue('file')[0];
		$format = $form_state->getValue('format');
		//print_r($format); exit;
		$path = \Drupal\file\Entity\File::load($uploaded_file_id)->getFileUri();
		if($format == 'text'){
			$params['langcode'] = $form_state->getValue('selected_langcode');
			$csv_count = $form_state->getValue('csv_count');
			$params['csv_labels'] = $form_state->getValue('csv_labels');
			$labels = explode(",", $params['csv_labels']);
			$handle = fopen(drupal_realpath($path), "r");
			$row = fgetcsv($handle);
			foreach ($row as $i => $header) {
				$columns[$i] = trim($header);
			}
			if(count($columns) != $csv_count){
				drupal_set_message($this->t('Invalid Number of CSV Headers. CSV columns should be in the order '.$params['csv_labels']), 'error');
				return;
			}
			while ($row = fgetcsv($handle)) {	
				$operations[] = array(
					'\Drupal\heritage_bulk_upload\ImportContent::importContent', // The function to run on each row
					array($params, $row), 
				);
				$index++;
			}
			if($index == 0) {
				drupal_set_message($this->t('CSV file seems to be empty'), 'error');
				return;
			}
		}
		else if($format == 'audio'){
			Zip::extract("public://file_uploads/audio/extract", $path);
			$uploaded_files = [];
			$d = dir("/"); // dir to scan
			while (false !== ($entry = $d->read())) { // mind the strict bool check!
				if ($entry[0] == '.') continue; // ignore anything starting with a dot
				$uploaded_files[] = $entry;
			}
			$d->close();
			sort($uploaded_files); // or whatever desired
			print_r($uploaded_files);exit;
		}
		$batch = array(
			'title' => t('processing...'),
			'operations' => $operations,
			'finished' => '\Drupal\heritage_bulk_upload\ImportContent::importContentFinishedCallback',
		);
		batch_set($batch);
	}
	
	/**
	* Ajax callback function
	*/
	public function _ajax_source_callback(array $form, FormStateInterface $form_state) {
		return $form['text_info']['fieldset']['source_formats'];
		


	}
}

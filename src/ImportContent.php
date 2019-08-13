<?php

namespace Drupal\heritage_bulk_upload;

use \Drupal\node\Entity\Node;

class ImportContent {
	/**
	 * Batch operation for bulk importing content to a heritage text
	 * @param params
     *		Array with textid, sourceid and text labels
	 * @param row
     *		Content to be imported
	 */
	public static function importContent($params, $row, &$context){
		$node_info = \Drupal\node\Entity\Node::load($params['text']);
		$parent_tid = 0;
		$curr_parent_tid = 0;
		$text_machine_name = $node_info->field_machine_name->value;
		$labels = explode(",", $params['csv_labels']);
		$index = 0;
		$title = '';
		$content = $row[count($labels) - 2];
		if(isset($content)){
			//get the taxonomy id of the level to which the content needs to be added
			while($index <= count($labels)-3){
				$level_name = trim($labels[$index].' '.$row[$index]);
				if($parent_tid == 0){
					$term_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", array(':vid' => $text_machine_name, ':name' => $level_name))->fetchField();
				}
				else{
					$term_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", array(':vid' => $text_machine_name, ':name' => $level_name, ':parent_tid' => $parent_tid))->fetchField();
				}
				$curr_parent_tid = $parent_tid;
				$parent_tid = $term_id;
				$index++;
				if($index == count($labels)-2) $title = $title.$row[$index-1];
				else $title = $title.$row[$index-1].'.';
			}
			$field_name = 'field_'.$text_machine_name.'_'.$params['source_id'].'_text';
			
			//check whether a node exists for this term_id or not

			$nid = db_query("SELECT entity_id FROM `node__field_positional_index` WHERE field_positional_index_target_id = :term_id AND bundle = :bundle AND langcode = :langcode", array(':term_id' => $term_id, ':bundle' => $text_machine_name, ':langcode' => $row[count($labels) - 1]))->fetchField();
			if(isset($nid) && $nid > 0){
				$node = \Drupal\node\Entity\Node::load($nid);    
				$node->{$field_name}->value = $content;
				$node->{$field_name}->format = 'full_html';
				$node->{$field_name}->langcode = $row[count($labels) - 1];
				$node->save();
			}
			else{
				$node = entity_create('node',
							[
								'type' => $text_machine_name,
								'title' => $title,
								$field_name => array('value' => $content,'format' => 'full_html'),
								'field_positional_index' => array(array('target_id' => (int) $curr_parent_tid), array('target_id' => (int) $term_id)),
								'langcode' => $row[count($labels) - 1]
							]
						);  
				$node->save();
			}
		}
		// Store some results for post-processing in the 'finished' callback.
		$context['results'][] = $title;
		$context['results']['textname'] = $text_machine_name;

		// Optional message displayed under the progressbar.
		$context['message'] = t('Running Batch');
	}

	/**
	 * Batch 'finished' callback used by importUsers.
	 */
	function importContentFinishedCallback($success, $results, $operations) {
		// The 'success' parameter means no fatal PHP errors were detected. All
		// other error management should be handled using 'results'.
		if ($success) $message = "Content Imported";
		else $message = t('Content Imported with error.');
		drupal_set_message($message);
	}

}

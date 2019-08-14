<?php

namespace Drupal\heritage_bulk_upload;

use Drupal\node\Entity\Node;

/**
 * Implements content import.
 */
class ImportContent {

  /**
   * Batch operation for bulk importing content to a heritage text.
   *
   * @param array params
   *   Array with textid, sourceid and text labels
   * @param array row
   *   Content to be imported
   */
  public static function importContent(array $params, array $row, &$context) {
    $node_info = Node::load($params['text']);
    $parent_tid = 0;
    $curr_parent_tid = 0;
    $text_machine_name = $node_info->field_machine_name->value;
    $labels = explode(",", $params['csv_labels']);
    $index = 0;
    $title = '';
    //$content = $row[count($labels) - 2];

    // Since content is the last column
    $content = $row[count($labels) - 1];
    if (isset($content)) {
      // Get the taxonomy id of the level to which the content needs to be added.
      // Since there are 3 columns now so count($labels) - 3 needs to be changed
      while ($index <= count($labels) - 2) {
        $level_name = trim($labels[$index] . ' ' . $row[$index]);
        if ($parent_tid == 0) {
          $term_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $level_name])->fetchField();
        }
        else {
          $term_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => $text_machine_name, ':name' => $level_name, ':parent_tid' => $parent_tid])->fetchField();
        }
        $curr_parent_tid = $parent_tid;
        $parent_tid = $term_id;
        $index++;
        // if ($index == count($labels) - 2)

        // This loop shows the title as 1.1 
        // It collects information from csv, if column Chapter and Sloka has
        // 1,1 respectively, then title shows as 1.1
        if ($index == count($labels) - 1) {
          $title = $title . $row[$index - 1];
        }
        else {
          $title = $title . $row[$index - 1] . '.';
        }
      }
      $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_text';

      // Check whether a node exists for this term_id or not.
      //$nid = db_query("SELECT entity_id FROM `node__field_positional_index` WHERE field_positional_index_target_id = :term_id AND bundle = :bundle AND langcode = :langcode", [':term_id' => $term_id, ':bundle' => $text_machine_name, ':langcode' => $row[count($labels) - 1]])->fetchField();
      $nid = db_query("SELECT entity_id FROM `node__field_positional_index` WHERE field_positional_index_target_id = :term_id AND bundle = :bundle", [':term_id' => $term_id, ':bundle' => $text_machine_name])->fetchField();
      if (isset($nid) && $nid > 0) {
        $node = Node::load($nid);
        $node->{$field_name}->value = $content;
        $node->{$field_name}->format = 'full_html';
       // $node->{$field_name}->langcode = $row[count($labels) - 1];
        $node->save();
      }
      else {
        $node = entity_create('node',
          [
            'type' => $text_machine_name,
            'title' => $title,
            $field_name => ['value' => $content, 'format' => 'full_html'],
            'field_positional_index' => [['target_id' => (int) $curr_parent_tid], ['target_id' => (int) $term_id]],
            //'langcode' => $row[count($labels) - 1],
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
  public function importContentFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = "Content Imported";
    }
    else {
      $message = $this->t('Content Imported with error.');
    }
    drupal_set_message($message);
  }

}

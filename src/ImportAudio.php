<?php

namespace Drupal\heritage_bulk_upload;

use Drupal\node\Entity\Node;

/**
 *
 */
class ImportAudio {

  /**
   *
   */
  public static function importAudio($upload, $params, &$context) {
    // Load the node.
    $node_info = Node::load($params['text']);
    // $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $info_present])->fetchField();
    $parent_tid = 0;
    $curr_parent_tid = 0;

    $text_machine_name = $node_info->field_machine_name->value;
    $level_labels = explode(',', $node_info->field_level_labels->value);
    $numLevels = count($level_labels);
    // $index = 0;
    if (isset($upload)) {
      $file_array = explode("/", $upload);
      $uploaded_file_name = end($file_array);
      $file_level_array = explode('.', $uploaded_file_name);
      // Remove '.mp3' from the array.
      array_pop($file_level_array);
      // print_r($upload);print_r($file_level_array);print_r($numLevels);
      if ($numLevels == count($file_level_array)) {
        $title_array = explode('.mp3', $uploaded_file_name);
        $title = $title_array[0];
        $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_audio';
        $data = file_get_contents($upload);
        $file = file_save_data($data, 'public://' . $text_machine_name . '/' . $uploaded_file_name, FILE_EXISTS_REPLACE);
        $contentId = db_query("SELECT nid FROM `node_field_data` WHERE title = :title AND type = :textname", [':title' => $title, ':textname' => $text_machine_name])->fetchField();
        if (isset($contentId) && $contentId > 0) {
          // Update the node.
          $contentNodeInfo = Node::load($contentId);
          $contentNodeInfo->set($field_name, $file->id());
          $contentNodeInfo->save();
        }
        else {
          $position_index_array = explode('.', $title);
          // print_r($title);
          //print_r($position_index_array);exit;
          $taxonomy_ids = [];
          $i = 0;
          for ($j = 0; $j < $numLevels; $j++) {
            if ($j == 0) {
              $position = $position_index_array[$j];
            }
            else {
              $position = $position . '.' . $position_index_array[$j];
            }
            $taxonomy_ids[$j]['target_id'] = db_query("SELECT entity_id FROM `taxonomy_term__field_position` WHERE bundle = :bundle AND field_position_value = :position", [':bundle' => $text_machine_name, ':position' => $position])->fetchField();
          }
          // print_r($taxonomy_ids);exit;
          $node = Node::create(
            [
              'type' => $text_machine_name,
              'title' => $title,
              $field_name => ['target_id' => $file->id()],
              'field_positional_index' => $taxonomy_ids,
              'langcode' => 'dv',
            ]
          );
          $node->save();
          // Create the node.
          
          
          /* if ($numLevels == 1) {
            // Find the chapter name.
            // $var = explode('.', $title);.
            $chapter_name = $level_labels[0] . ' ' . $file_level_array[0];

            // Find out the chapter tid.
            $chapter_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $chapter_name])->fetchField();

            // Create a node with chapter tid.
            $node = entity_create(
                   [
                     'type' => $text_machine_name,
                     'title' => $title,
                     $field_name => ['target_id' => $file->id()],

                     'field_positional_index' => [['target_id' => (int) $chapter_tid]],
                     'langcode' => 'dv',
                   ]

            );

            // $node->save();
          }

          else if ($numLevels == 2) {

            // Find the chapter name.
            // $var = explode(',', $title);.
            $chapter_name = $level_labels[0] . ' ' . $file_level_array[0];
            $sloka_name = $level_labels[1] . ' ' . $file_level_array[1];

            // Find the chapter tid.
            $chapter_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $chapter_name])->fetchField();

            // Find the sloka tid.
            $sloka_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => $text_machine_name, ':name' => $sloka_name, ':parent_tid' => $chapter_tid])->fetchField();

            // Create the node with Chapter tid and sloka tid.
            $node = entity_create(
                    [
                      'type' => $text_machine_name,
                      'title' => $title,
                      $field_name => ['target_id' => $file->id()],

                      'field_positional_index' => [['target_id' => (int) $chapter_tid], ['target_id' => (int) $sloka_tid]],
                      'langcode' => 'dv',
                    ]

            );

            // $node->save();
          }

          else if ($numLevels == 3) {
            $var = explode(',', $title);
            $kanda_name = $level_labels[0] . ' ' . $file_level_array[0];
            $sarga_name = $level_labels[1] . ' ' . $file_level_array[1];
            $sloka_name = $level_labels[2] . ' ' . $file_level_array[2];

            // Find the kanda tid.
            $kanda_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $kanda_name])->fetchField();

            $sarga_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => $text_machine_name, ':name' => $sarga_name, ':parent_tid' => $kanda_tid])->fetchField();

            $sloka_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => $text_machine_name, ':name' => $sloka_name, ':parent_tid' => $sarga_tid])->fetchField();

            $node = entity_create('node', [

              'type' => $text_machine_name,
              'title' => $title,
              'langcode' => 'dv',

              'field_positional_index' => [['target_id' => (int) $kanda_tid], ['target_id' => (int) $sarga_tid], ['target_id' => (int) $sloka_tid]],

              $field_name => ['target_id' => $file->id()],

            ]
                        );

            // $node->save();
          } */
        }
          $context['results'][] = $title;
          $context['results']['textname'] = $text_machine_name;
      }
      // $l = explode(".", $upload);
      // $l should look like $l = [1-1,mp3]
      // $labels = explode("-", $l[0]);
      // Case for text with 1 level.
      /* if (count($level_labels) == 1) {


      $chapter_name = $level_labels[0] . ' ' . $l[0];

      $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_audio';
      $chapter_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $chapter_name])->fetchField();
      // print($chapter_tid);

      $data = file_get_contents("public://file_uploads/audio/extract/audio/" . $upload);
      $file = file_save_data($data, "public://file_uploads/audio/extract/audio/" . $upload, FILE_EXISTS_REPLACE);

      // Check whether a node exists for this term_id or not.
      $nid = db_query("SELECT entity_id FROM `node__field_positional_index` WHERE field_positional_index_target_id = :term_id AND bundle = :bundle AND langcode = :langcode", [':term_id' => $chapter_tid, ':bundle' => $text_machine_name, ':langcode' => 'dv'])->fetchField();

      //  $title = $labels[0];
      $title = $l[0];


      if (isset($nid) && $nid > 0) {
      $node = Node::load($nid);
      $node->set($field_name, $file->id());

      $node->save();
      }

      else {
      $node = entity_create(
      [
      'type' => $text_machine_name,
      'title' => $title,
      $field_name => ['target_id' => $file->id()],

      'field_positional_index' => [['target_id' => (int) $chapter_tid]],
      'langcode' => 'dv',
      ]

      );

      }

      }

      // Case for text with 2 levels.
      if (count($level_labels) == 2) {

      $labels = explode("-", $l[0]);


      $chapter_name = $level_labels[0] . ' ' . $labels[0];
      $sloka_name = $level_labels[1] . ' ' . $labels[1];




      //$chapter_name = 'Chapter ' . $labels[0];
      // $sloka_name = 'Sloka ' . $labels[1];

      $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_audio';
      // Check if the entity id getting selected right.
      $chapter_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $chapter_name])->fetchField();

      $sloka_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => $text_machine_name, ':name' => $sloka_name, ':parent_tid' => $chapter_id])->fetchField();

      // $data = file_get_contents($upload);
      $data = file_get_contents("public://file_uploads/audio/extract/audio/" . $upload);

      $file = file_save_data($data, "public://file_uploads/audio/extract/audio/" . $upload, FILE_EXISTS_REPLACE);
      // print_r($file);exit;
      // Check whether a node exists for this term_id or not.
      $nid = db_query("SELECT entity_id FROM `node__field_positional_index` WHERE field_positional_index_target_id = :term_id AND bundle = :bundle AND langcode = :langcode", [':term_id' => $sloka_id, ':bundle' => $text_machine_name, ':langcode' => 'dv'])->fetchField();
      $title = $labels[0] . $labels[1];

      if (isset($nid) && $nid > 0) {
      $node = Node::load($nid);
      $node->set($field_name, $file->id());

      $node->save();
      }
      else {
      $node = entity_create(
      [
      'type' => $text_machine_name,
      'title' => $title,
      $field_name => ['target_id' => $file->id()],

      'field_positional_index' => [['target_id' => (int) $chapter_id], ['target_id' => (int) $sloka_id]],
      'langcode' => 'dv',
      ]

      );

      }
      }

      // Case for text with 3 levels
      if(count($labels) == 3) {
      $level_labels = $node_info->field_level_labels->value;

      $kanda_name = $level_labels[0] . ' ' . $labels[0];
      $sarga_name = $level_labels[1] . ' ' . $labels[1];
      $sloka_name = $level_labels[2] . ' ' . $labels[2];

      $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_audio';

      $kanda_tid = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => $text_machine_name, ':name' => $kanda_name])->fetchField();
       */

    }
  //  $context['results'][] = $title;
  //  $context['results']['textname'] = $text_machine_name;
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch');

  }

  /**
   * Batch 'finished' callback used by importUsers.
   */
  public static function importAudioFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = "Audio Imported";
    }
    else {
      $message = 'Audio Imported with error.';

    }
    drupal_set_message($message);
  }

}

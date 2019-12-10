<?php

namespace Drupal\heritage_bulk_upload;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;


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
    $parent_tid = 0;
    $curr_parent_tid = 0;

    $text_machine_name = $node_info->field_machine_name->value;
    $title = '';
    // $index = 0;
    if (isset($upload)) {

      $l = explode(".", $upload);
      // $l should look like $l = [1-1,mp3]
      $labels = explode("-", $l[0]);

      $chapter_name = 'Chapter ' . $labels[0];
      $sloka_name = 'Sloka ' . $labels[1];

      $field_name = 'field_' . $text_machine_name . '_' . $params['source_id'] . '_audio';
      // Check if the entity id getting selected right.
      $chapter_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name", [':vid' => 'gita', ':name' => $chapter_name])->fetchField();

      $sloka_id = db_query("SELECT tid FROM `taxonomy_term_field_data` WHERE vid = :vid AND name = :name AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent_tid)", [':vid' => 'gita', ':name' => $sloka_name, ':parent_tid' => $chapter_id])->fetchField();

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
    $context['results'][] = $title;
    $context['results']['textname'] = $text_machine_name;

    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch');

  }

}

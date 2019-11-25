<?php

namespace Drupal\heritage_bulk_upload\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
// use Drupal\Core\Archiver\Zip;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Archiver\ArchiverManager;


/**
 * Implementing the import content text form.
 */
class ImportContentText extends FormBase {

  /**
   * The entity type manager.
   *
   * @var entityTypeManager\Drupal\Core\Entity\EntityTypeManagerInterface
   */

  protected $entityTypeManager;

  /**
   * Drupal\Core\File\FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */

  protected $fileSystem;

   /**
   * Drupal\Core\Archiver\ArchiverManager definition.
   *
   * @var \Drupal\Core\Archiver\ArchiverManager
   */

  protected $pluginManagerArchiver;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager,FileSystemInterface $file_system, ArchiverManager $plugin_manager_archiver) {

    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $file_system;
    $this->pluginManagerArchiver = $plugin_manager_archiver;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('plugin.manager.archiver')

    );
  }

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
    // $node_info = Node::load($node);
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_info = $node_storage->load($node);
    $description = '';
    $labels = $node_info->field_level_labels->value;
    $labels_array = explode(',', $labels);
    $label_count = count($labels_array);
    for ($i = 0; $i < count($labels_array); $i++) {
      if ($i < $label_count - 1) {
        $description = $description . $labels_array[$i] . ', ';
      }
      else {
        $description = $description . $labels_array[$i];
      }
    }
    // $description = $description . ', Content, Language';
    // If language gets removed, no need for Language here
    $description = $description . ', Content';

    // $csvCount = $label_count + 2;
    $csvCount = $label_count + 1;
    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $text_id,
    ];
    $form['text_info'] = [
      '#type' => 'container',
      '#prefix' => '<div id="text-info">',
      '#suffix' => '</div>',
    ];
    if (isset($text_id) && $text_id > 0) {
      $validators = [
        'file_validate_extensions' => ['csv '],
      ];
      $validators_zip = [
        'file_validate_extensions' => ['zip '],
      ];
      $form['text_info']['fieldset'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Select the Target Source'),
        '#description' => $this->t('Choose the source into which you are importing content'),
      ];
      $sources = [];
      $source_values = db_query("SELECT id, title FROM `heritage_source_info` WHERE text_id = :text_id", ['text_id' => $text_id])->fetchAll();
      foreach ($source_values as $key => $value) {
        $sources[$value->id] = $value->title;
      }
      $form['text_info']['fieldset']['sources'] = [
        '#type' => 'select',
        '#title' => $this->t('Select the Source to which content is imported'),
        '#required' => TRUE,
        '#options' => $sources,
        '#default_value' => isset($form['text_info']['fieldset']['sources']['widget']['#default_value']) ? $form['text_info']['fieldset']['sources']['widget']['#default_value'] : NULL,
        '#ajax' => [
          'event' => 'change',
          'wrapper' => 'source-formats',
          'callback' => '::_ajax_source_callback',
        ],
      ];

      if (!empty($form_state->getTriggeringElement())) {

        // Get the corresponding sourceid from the
        // Input in the Choose source field.
        $sourceid = $form_state->getUserInput()['sources'];

      }
      if (!isset($sourceid)) {
        $sourceid = $form['text_info']['fieldset']['sources']['widget']['#default_value'];
      }
      // print_r($sourceid);
      $form['text_info']['fieldset']['source_formats'] = [
        '#type' => 'container',
        '#prefix' => '<div id="source-formats">',
        '#suffix' => '</div>',
      ];
      if (isset($sourceid)) {

        $format = db_query("SELECT format FROM `heritage_source_info` WHERE id = :sourceid", [':sourceid' => $sourceid])->fetchField();

        $form['text_info']['fieldset']['format'] = [
          '#type' => 'hidden',
          '#value' => $format,

        ];
      }
      if (isset($format) && $format == 'text') {
        $form['text_info']['fieldset']['source_formats']['selected_langcode'] = [
          '#type' => 'language_select',
          '#title' => $this->t('Language'),
          '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
          // '#languages' => LanguageInterface::STATE_ALL,
        ];
        $form['text_info']['fieldset']['source_formats']['file'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Upload the Content in CSV format'),
          '#size' => 20,
          // '#description' => t('CSV file (eg: ' . $description . '). Please maintain the order of the fields.'),
          '#description' => $this->t('CSV file (eg: @description). Please maintain the order of the fields.', ['@description' => $description]),
          '#upload_location' => 'public://file_uploads/',
          '#upload_validators' => $validators,
        ];
        $form['text_info']['fieldset']['source_formats']['csv_count'] = [
          '#type' => 'hidden',
          '#value' => $csvCount,
        ];
        $form['text_info']['fieldset']['source_formats']['csv_labels'] = [
          '#type' => 'hidden',
          '#value' => $description,
        ];
      }
      elseif (isset($format) && $format == 'audio') {
        $form['text_info']['fieldset']['source_formats']['audiofiles'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Upload zip file containing audios in mp3 format'),
          '#upload_location' => 'public://file_uploads/audio/',
          '#upload_validators' => $validators_zip,
        ];
      }
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Content'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */


  

  /**
   * Map content to the corresponding fields.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params['text'] = $form_state->getValue('text');
    $index = 0;
    $params['source_id'] = $form_state->getValue('sources');
    $params['format'] = $form_state->getValue('format');
    $uploaded_file_id = $form_state->getValue('file')[0];
    $uploaded_audio_file_id = $form_state->getValue(['audiofiles',0]);
    // $uploaded_audio_file_id->setPermanent();
    // $uploaded_audio_file_id->save();
    $format = $form_state->getValue('format');
    // print_r($format); exit;.
    // $path = File::load($uploaded_file_id)->getFileUri();
    //$path_audio = File::load($uploaded_audio_file_id)->getFileUri();

    if ($format == 'text') {
      $path = File::load($uploaded_file_id)->getFileUri();
      $params['langcode'] = $form_state->getValue('selected_langcode');

      $csv_count = $form_state->getValue('csv_count');
      $params['csv_labels'] = $form_state->getValue('csv_labels');
      // print_r('csv_labels');exit;.
      $labels = explode(",", $params['csv_labels']);
      $handle = fopen(drupal_realpath($path), "r");
      $row = fgetcsv($handle);
      foreach ($row as $i => $header) {
        $columns[$i] = trim($header);
      }
      if (count($columns) != $csv_count) {
        drupal_set_message($this->t('Invalid Number of CSV Headers. CSV columns should be in the order @params'), 'error', ['@params' => $params['csv_labels']]);
        return;
      }
      while ($row = fgetcsv($handle)) {
        $operations[] = [
        // The function to run on each row.
          '\Drupal\heritage_bulk_upload\ImportContent::importContent',
        [$params, $row],
        ];
        $index++;
      }
      if ($index == 0) {
        drupal_set_message($this->t('CSV file seems to be empty'), 'error');
        return;
      }
    }
    elseif ($format == 'audio') {
     // $fileRealPath = \Drupal\file\Entity\File::load($uploaded_audio_file_id)->getFileUri();
     // $fileRealPath = File::load($uploaded_audio_file_id)->getFileUri();
      if (is_numeric($uploaded_audio_file_id)) {
        $file = $this->entityTypeManager->getStorage('file')->load($uploaded_audio_file_id);
      }
      if($file) {


      $fileRealPath = $this->fileSystem->realpath($file->getFileUri());


      $zip = $this->pluginManagerArchiver->getInstance(['filepath' => $fileRealPath]);
      // A file will be extracted to the public folder.
      $zip->extract('public://file_uploads/audio/extract/');
        $uploaded_files = [];
         $data = file_get_contents('public://file_uploads/audio/extract/');
        $file = file_save_data($data, 'public://file_uploads/audio/extract/', FILE_EXISTS_REPLACE);


    
      
      //$uploaded_files = [];
      // Dir to scan.
      $d = dir("public://file_uploads/audio/extract/audio");
      // Mind the strict bool check!
      while (FALSE !== ($entry = $d->read())) {
        if ($entry[0] == '.') {
          // Ignore anything starting with a dot.
          continue;
        }
        $uploaded_files[] = $entry;
      }
      $d->close();
      // Or whatever desired.
      sort($uploaded_files);
      //print_r($uploaded_files);exit;
       // foreach ($uploaded_files as $upload) {
       //   $operations[] = [
       //    // The function to run on each file.
       //      '\Drupal\heritage_bulk_upload\ImportAudio::importAudio', [$upload, $params],

       //    ];

       // }
        $operations[] = [
          // The function to run on each file.
            '\Drupal\heritage_bulk_upload\ImportAudio::importAudio', [$uploaded_files[0], $params],

          ];
        
    }
  
    $batch = [
      'title' => $this->t('processing...'),
      'operations' => $operations,
      'finished' => '\Drupal\heritage_bulk_upload\ImportContent::importContentFinishedCallback',
    ];
    batch_set($batch);
  }
}

  /**
   * Ajax callback function.
   */
  public function _ajax_source_callback(array $form, FormStateInterface $form_state) {
    return $form['text_info']['fieldset']['source_formats'];

  }

}

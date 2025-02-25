<?php

namespace Drupal\bulk_file_to_media\Drush;

use Drush\Commands\DrushCommands;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\Entity\User;

/**
 * A Drush command file.
 */
class BulkFileToMediaDrushCommands extends DrushCommands {

  /**
   * Converts existing files to media entities.
   *
   * @command bulk_file_to_media:convert
   * @aliases bftm
   */
  public function convertFilesToMedia() {
    $query = \Drupal::entityQuery('file')->accessCheck(FALSE);
    $fids = $query->execute();

    if (empty($fids)) {
      $this->logger()->notice('No files found to convert.');
      return;
    }

    // Define supported file types and their respective media bundles.
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $doc_extensions = ['pdf', 'pptx', 'ppt', 'docx'];

    $counter = 0;
    $skipped = 0;

    foreach ($fids as $fid) {
      $file = File::load($fid);
      if (!$file) {
        continue;
      }

      $file_extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));

      if (in_array($file_extension, $image_extensions)) {
        $media_type = 'image';
        $source_field = 'field_media_image';
      } elseif (in_array($file_extension, $doc_extensions)) {
        $media_type = 'file';
        $source_field = 'field_media_file';
      } else {
        $skipped++;
        continue;
      }

      // Check if media type exists.
      $media_type_exists = \Drupal::entityQuery('media_type')
        ->condition('id', $media_type)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($media_type_exists)) {
        $this->logger()->warning("Media type '$media_type' does not exist. Skipping file: {$file->getFilename()}");
        $skipped++;
        continue;
      }

      // Check for existing media entity.
      $existing_media = \Drupal::entityQuery('media')
        ->condition($source_field, $fid)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($existing_media)) {
        continue;
      }

      $media_data = [
        'bundle' => $media_type,
        'name' => $file->getFilename(),
        'uid' => User::load(1)->id(),
        'status' => 1,
        $source_field => [
          'target_id' => $fid,
        ],
      ];

      if ($media_type == 'image') {
        $media_data[$source_field]['alt'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
      }

      $media = Media::create($media_data);
      $media->save();

      $counter++;
    }

    $this->logger()->success("Converted $counter files to media entities. Skipped $skipped unsupported files.");
  }
}
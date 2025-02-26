<?php

namespace Drupal\bulk_file_to_media\Drush;

use Drush\Commands\DrushCommands;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * A Drush command to update missing alt text for media entities.
 */
class BulkUpdateMediaAltTextDrushCommands extends DrushCommands {

  /**
   * Updates missing alt text for media images.
   *
   * @command bulk_file_to_media:update-alt
   * @aliases bftma
   * @usage bulk_file_to_media:update-alt
   *   Loops through all media images and sets the alt text if it's empty.
   */
  public function updateMediaAltText() {
    // Query all media of type 'image'
    $media_ids = \Drupal::entityQuery('media')
      ->condition('bundle', 'image')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($media_ids)) {
      $this->logger()->notice('No media images found.');
      return;
    }

    $updated_count = 0;

    foreach ($media_ids as $mid) {
      $media = Media::load($mid);
      if (!$media) {
        continue;
      }

      // Get the file reference from field_media_image.
      if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
        continue;
      }

      $file_id = $media->get('field_media_image')->target_id;
      $file = File::load($file_id);

      if (!$file) {
        continue;
      }

      // Get the alt text field.
      $alt_field = $media->get('field_media_image')->first();

      // Check if alt text is missing.
      if (empty($alt_field->alt)) {
        $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $alt_field->alt = $filename;
        $media->save();

        $this->logger()->notice("Updated alt text for media ID: {$mid} -> '$filename'");
        $updated_count++;
      }
    }

    $this->logger()->success("Updated alt text for $updated_count media images.");
  }
}

<?php

namespace Drupal\vcf\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Laminas\Diactoros\Response\JsonResponse;

class ImportController extends ControllerBase {

  public function test(): JsonResponse {
    $vcf = \Drupal::service('vcf.vcf')->vcfToArray();
    file_put_contents(DRUPAL_ROOT . "/../vcf.json", json_encode($vcf));
    $imported = \Drupal::service('vcf.import')->importAsNodes($vcf);
    return new JsonResponse($vcf);
  }

  public function deleteAllContent(): JsonResponse {
    return new JsonResponse(['error' => 'Only admin can delete all content.']);

    $query = \Drupal::entityQuery('node');
    $nids = $query->execute();
    foreach ($nids as $nodeId) {
      try {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }

      try {
        $node->delete();
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }
    }

    // Delete all media entities.
    $query = \Drupal::entityQuery('media');
    $mids = $query->execute();
    foreach ($mids as $mediaId) {
      try {
        $media = \Drupal::entityTypeManager()->getStorage('media')->load($mediaId);
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }

      try {
        $media->delete();
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }
    }

    // Delete all files.
    $query = \Drupal::entityQuery('file');
    $fids = $query->execute();
    foreach ($fids as $fileId) {
      try {
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($fileId);
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }

      try {
        $file->delete();
      } catch (\Throwable $e) {
        return new JsonResponse($e->getMessage());
      }
    }

    return new JsonResponse(['success' => TRUE]);
  }

}

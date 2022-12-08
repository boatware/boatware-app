<?php

namespace Drupal\vcf\Service;

use Drupal\Core\File\FileSystemInterface;

class Vcf {

  /**
   * Converts a VCF file to an array.
   *
   * @param $vcf
   *   The raw file contents of a VCF file.
   *
   * @return array
   *   An array of VCF data.
   */
  public function vcfToArray($vcf = NULL): array {
    $fallbackFile = DRUPAL_ROOT . "/../Beispiel-Export-Setup.vcf";

    if (empty($vcf)) {
      if (file_exists($fallbackFile)) {
        $vcf = file_get_contents($fallbackFile);
      }
      else {
        return [];
      }
    }

    $lines = explode("\n", $vcf);

    // Clean lines
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);

    $contacts = [];
    $contact = [];
    foreach ($lines as $key => $line) {

      $contact[] = $line;
      if ($line === "END:VCARD") {
        $contacts[] = $contact;
        $contact = [];
      }
    }

    foreach ($contacts as $key => $contact) {
      $photo = NULL;
      $photoKey = NULL;
      foreach ($contact as $k => $line) {
        $line = explode(":", $line);

        if (!in_array($line[0], ['BEGIN', 'END', 'VERSION']) && !empty($line[1])) {
          $contact[$line[0]] = $line[1];
        }

        if (str_starts_with($line[0], 'PHOTO')) {
          $photo = $line[1];
          $photoKey = $line[0];
        }

        if (strlen($line[0]) > 70 && empty($line[1])) {
          $photo .= $line[0];
        }

        unset($contact[$k]);
      }

      if ($photo) {
        $contact[$photoKey] = $photo;
      }

      foreach ($contact as $k => $line) {
        if (empty($line)) {
          unset($contact[$k]);
        }

        if ($k === "PRODID") {
          $contact['generator'] = $line;
          unset($contact[$k]);
        }

        if ($k === "REV") {
          $contact['lastModified'] = $line;
          unset($contact[$k]);
        }

        if ($k === "UID") {
          $contact['id'] = $line;
          unset($contact[$k]);
        }

        if ($k === "FN") {
          $contact['name_formatted'] = $line;
          unset($contact[$k]);
        }

        if ($k === "N") {
          $separated = explode(";", $line);
          $contact['name']['family'] = $separated[0];
          $contact['name']['given'] = $separated[1];
          $contact['name']['additional'] = $separated[2];
          $contact['name']['prefix'] = $separated[3];
          $contact['name']['suffix'] = $separated[4];
          unset($contact[$k]);
        }

        if ($k === "NICKNAME") {
          $contact['nickname'] = $line;
          unset($contact[$k]);
        }

        if ($k === "BDAY") {
          $contact['birthday'] = \DateTime::createFromFormat('Y-m-d', $line);
          unset($contact[$k]);
        }

        if (str_starts_with($k, 'PHOTO')) {
          $keys = explode(";", $k);
          $contact['photo']['encoding'] = $keys[1];
          $contact['photo']['type'] = $keys[2];

          $filetypeArray = explode("=", $keys[2]);
          $filetype = $filetypeArray[1];
          $filetype = strtolower($filetype);
          $filename = md5($line) . '.' . $filetype;
          $path = 'public://' . $filename;
          $realpath = \Drupal::service('file_system')->realpath($path);

          // Delete file if it already exists.
          if (file_exists($realpath)) {
            \Drupal::service('file_system')->delete($realpath);
          }

          // Save the photo to the public files' directory.
          $image = base64_decode($line);
          \Drupal::service('file_system')->saveData($image, $path, FileSystemInterface::EXISTS_REPLACE);

          /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
          $fileRepository = \Drupal::service('file.repository');
          $file = $fileRepository->writeData($image, $path, FileSystemInterface::EXISTS_REPLACE);
          $file = \Drupal\file\Entity\File::load($file->id());
          $file->setPermanent();
          $file->save();
          $contact['photo']['fid'] = $file->id();

          $contact['photo']['path'] = $realpath;

          unset($contact[$k]);
        }

        if (preg_match_all('/ADR;/', $k)) {
          // Extract address from vcf
          $keys = explode(";", $k);
          $index = isset($contact['address']) ? count($contact['address']) : 0;
          $contact['address'][$index]['type'] = $keys[1];

          $separated = explode(";", $line);
          $contact['address'][$index]['post-office-box'] = $separated[0] ?? "";
          $contact['address'][$index]['extended-address'] = $separated[1] ?? "";
          $contact['address'][$index]['street-address'] = $separated[2] ?? "";
          $contact['address'][$index]['locality'] = $separated[3] ?? "";
          $contact['address'][$index]['region'] = $separated[4] ?? "";
          $contact['address'][$index]['postal-code'] = $separated[5] ?? "";
          $contact['address'][$index]['country-name'] = $separated[6] ?? "";
          unset($contact[$k]);
        }

        if (preg_match_all('/^TEL;/', $k)) {
          // Extract phone number from vcf
          $keys = explode(";", $k);
          $index = isset($contact['phone']) ? count($contact['phone']) : 0;
          $contact['phone'][$index]['type'] = str_replace("type=", "", $keys[1]);
          $contact['phone'][$index]['number'] = $line;
          $contact['phone'][$index]['preferred'] = !empty(preg_match_all('/pref/', $k));
          unset($contact[$k]);
        }

        if (preg_match_all('/EMAIL;/', $k)) {
          // Extract email from vcf
          $keys = explode(";", $k);
          $index = isset($contact['email']) ? count($contact['email']) : 0;
          $contact['email'][$index]['type'] = str_replace("type=", "", $keys[1]);
          $contact['email'][$index]['address'] = $line;
          $contact['email'][$index]['preferred'] = !empty(preg_match_all('/pref/', $k));
          unset($contact[$k]);
        }
      }

      $contacts[$key] = $contact;
    }

    return $contacts;
  }

}

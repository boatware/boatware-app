<?php

namespace Drupal\vcf\Service;

use Drupal\file\Entity\File;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\node\Entity\Node;

class Import {

  public function importAsNodes(array $vcfArray): bool {
    foreach ($vcfArray as $key => $contact) {

      $payload = [
        'type' => 'contact',
        'title' => md5(json_encode($contact)),
      ];

      if (!empty($contact['name'])) {
        $aggregateName = "";

        if (!empty($contact['name']['prefix'])) {
          $payload['field_name_prefix'] = $contact['name']['prefix'];
          $aggregateName .= ' ' . $contact['name']['prefix'];
        }

        if (!empty($contact['name']['given'])) {
          $payload['field_first_name'] = $contact['name']['given'];
          $aggregateName .= ' ' . $contact['name']['given'];
        }

        if (!empty($contact['name']['additional'])) {
          $payload['field_additional_names'] = $contact['name']['additional'];
          $aggregateName .= ' ' . $contact['name']['additional'];
        }

        if (!empty($contact['name']['family'])) {
          $payload['field_last_name'] = $contact['name']['family'];
          $aggregateName .= ' ' . $contact['name']['family'];
        }

        if (!empty($contact['name']['suffix'])) {
          $payload['field_name_suffix'] = $contact['name']['suffix'];
          $aggregateName .= ' ' . $contact['name']['suffix'];
        }

        $aggregateName = trim($aggregateName);

      }

      if (!empty($contact['phone'])) {
        foreach ($contact['phone'] as $phone) {
          $node = Node::create([
            'type' => 'phone_number',
            'title' => $phone['number'],
            'field_number' => $phone['number'],
            'field_preferred' => $phone['preferred'] ? 1 : 0,
          ]);
          $node->save();
          $payload['field_phone'][] = ['target_id' => $node->id()];
        }
      }

      if (!empty($contact['email'])) {
        foreach ($contact['email'] as $email) {
          $node = Node::create([
            'type' => 'email',
            'title' => $email['address'],
            'field_address' => $email['address'],
            'field_preferred' => $email['preferred'] ? 1 : 0,
          ]);
          $node->save();
          $payload['field_emails'][] = ['target_id' => $node->id()];
        }
      }

      if (!empty($contact['address'])) {
        if (!empty($contact['address']['post-office-box'])) {
          $payload['field_post_office_box'] = $contact['address']['post-office-box'];
        }

        if (!empty($contact['address']['postal-code'])) {
          $payload['field_postal_code'] = $contact['address']['postal-code'];
        }

        if (!empty($contact['address']['street-address'])) {
          $address = $contact['address']['street-address'];
          $houseNumber = '';
          $street = '';
          $streetParts = explode(' ', $address);
          if (count($streetParts) > 1) {
            $houseNumber = array_pop($streetParts);
            $street = implode(' ', $streetParts);
          }
          else {
            $street = $address;
          }

          $payload['field_house_number'] = $houseNumber;
          $payload['field_street'] = $street;
        }

        if (!empty($contact['address']['region'])) {
          $payload['field_region'] = $contact['address']['region'];
        }

        if (!empty($contact['address']['locality'])) {
          $payload['field_locality'] = $contact['address']['locality'];
        }
      }

      if (!empty($contact['photo'])) {
        $fileId = $contact['photo']['fid'];
        if (!empty($fileId)) {
          $media = \Drupal::entityTypeManager()->getStorage('media')->create([
            'bundle' => 'image',
            'status' => 1,
            'name' => isset($aggregateName) && !empty($aggregateName) ? $aggregateName : md5(json_encode($contact)),
            'field_media_image' => [
              'target_id' => File::load($fileId)->id(),
            ],
          ]);
          $media->save();
          $payload['field_images'][] = ['target_id' => $media->id()];
        }
      }

      $node = Node::create($payload);
      $node->save();
    }

    return TRUE;
  }

}

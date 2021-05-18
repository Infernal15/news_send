<?php

namespace Drupal\news_send\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Process a queue.
 *
 * @QueueWorker(
 *   id = "ToSend",
 *   title = @Translation("News send queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class ToSend extends QueueWorkerBase {

  /**
   * Contains a function that processes the data received from the cron queue.
   *
   * @param int $data
   *   Contain node id.
   */
  public function processItem($data) {
    $time = date("H");
    if ($time >= 10 || $time < 18) {
      $service = \Drupal::service('news_send_services');
      $state = \Drupal::service('state');
      $service->sendTestMail($data, '0');
      $storageQueue = $state->get('news_send');
      if (empty($storageQueue)) {
        $storageQueue = [];
      }
      foreach ($storageQueue as $value) {
        if ($value['nid'] === $data) {
          $value['is_sent'] = TRUE;
        }
      }
    }
  }

}

<?php

namespace Drupal\news_send\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service that adds node to queue.
 */
class NewsSendServices {

  /**
   * Declaring EntityViewBuilder object.
   *
   * @var \Drupal\news_send\Services\EntityViewBuilder
   */
  private EntityViewBuilder $builder;

  /**
   * Declaring State object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Declaring QueueFactory object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queue;

  /**
   * Declaring RequestStack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request;

  /**
   * Declaring EntityTypeManagerInterface object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityType;

  /**
   * Declaring SendMail object.
   *
   * @var \Drupal\news_send\Services\SendMail
   */
  protected SendMail $mail;

  /**
   * Declaring Connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * NewsSendServices constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State object.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Request object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityType
   *   EntityType object.
   * @param \Drupal\news_send\Services\SendMail $mail
   *   SendMail object.
   * @param \Drupal\Core\Database\Connection $database
   *   Database Connection object.
   */
  public function __construct(StateInterface $state, QueueFactory $queue, RequestStack $request, EntityTypeManagerInterface $entityType, SendMail $mail, Connection $database) {
    $this->state = $state;
    $this->queue = $queue;
    $this->request = $request;
    $this->entityType = $entityType;
    $this->mail = $mail;
    $this->database = $database;
  }

  /**
   * Adds node to queue for send.
   *
   * @param int $nid
   *   Contain id of added/updated news.
   */
  public function nodeAddToQueue(int $nid): void {

    // Create a queue.
    $queue = $this->queue->get('ToSend');
    $queue->createQueue();

    // Get send list from state.
    $storageQueue = $this->state->get('news_send');
    if (empty($storageQueue)) {
      $storageQueue = [];
    }
    $checker = FALSE;

    // If node in send list then skip it.
    foreach ($storageQueue as $value) {
      if ($nid === $value['nid']) {
        $checker = TRUE;
        break;
      }
    }

    // If node is not in send list push it there.
    if (!$checker) {
      $timeStamp = time();
      $queue_item = [
        'nid' => $nid,
        'insert_timestamp' => $timeStamp,
        'is_sent' => FALSE,
      ];
      $queue->createItem($nid);
      array_push($storageQueue, $queue_item);
    }

    // Return send list to state.
    $this->state->set('news_send', $storageQueue);
  }

  /**
   * Clears ToSend queue of sent nodes.
   */
  public function clearNewsQueue():void {
    // Get send list from state.
    $storageQueue = $this->state->get('news_send');
    if (empty($storageQueue)) {
      $storageQueue = [];
    }

    // Delete sent nodes from send queue.
    foreach ($storageQueue as $key => $value) {
      if ($value['is_sent']) {
        unset($storageQueue[$key]);
      }
    }
  }

  /**
   * Build email from needed nodes.
   *
   * @param int $item
   *   Builds HTML markup by node id.
   */
  public function emailBuild(int $item) {
    $node = $this->entityType->getStorage('node')->load($item);
    $builder = $this->entityType->getViewBuilder('node');
    $pre_render = $builder->view($node, 'teaser');
    $render = render($pre_render);
    $host = $this->request->getCurrentRequest()->getSchemeAndHttpHost();
    $subStr = 'img src="';
    $render = str_replace($subStr, $subStr . $host, $render);
    $subStr = 'a href="';
    $replaceStr = 'a target="_blank" href="';
    return str_replace($subStr, $replaceStr . $host, $render);
  }

  /**
   * Build email from needed nodes.
   *
   * @param array $item
   *   Builds HTML markup by node id.
   */
  public function emailBuildMultiple(array $item) {
    $node = $this->entityType->getStorage('node')->loadMultiple($item);
    $builder = $this->entityType->getViewBuilder('node');
    $pre_render = $builder->viewMultiple($node, 'teaser');
    $render = render($pre_render);
    $host = $this->request->getCurrentRequest()->getSchemeAndHttpHost();
    $subStrIm = 'img src="';
    $subStrA = 'a href="';
    $replaceStr = 'a target="_blank" href="';

    $render = str_replace($subStrIm, $subStrIm . $host, $render);
    return str_replace($subStrA, $replaceStr . $host, $render);
  }

  /**
   * Push to cron query nid news if we have not this in query.
   *
   * @param int $item
   *   Contain id of added/updated news.
   * @param string $daily
   *   Type of mailing, 0 for hourly, 1 for daily.
   */
  public function sendTestMail(int $item, string $daily):array {
    // Build mail params.
    $params['subject'] = 'Our last news';
    if ($daily === '0') {
      $params['content'] = $this->emailBuild($item);
    }
    elseif ($daily === '1') {
      $item = $this->getLatestNewsForDay();
      $params['content'] = $this->emailBuildMultiple($item);
    }
    if (empty($params['content'])) {
      return [];
    }
    $params['users'] = $this->getEmailsList();
    // Send mail via service.
    $this->mail->sendMail($params, $daily);
    return [];
  }

  /**
   * Gets list of emails to mailing.
   *
   * @return array
   *   Returns array of emails for mailing.
   */
  public function getEmailsList():array {
    $query = $this->database->select('news_send', 'ns')
      ->fields('ns');

    $result = $query->execute()->fetchAll();

    $emails = [];
    foreach ($result as $value) {
      array_push($emails, [$value->email, $value->uuid, $value->daily]);
    }
    return $emails;
  }

  /**
   * Gets list of news for daily mailing.
   *
   * @return array
   *   Returns array of news.
   */
  public function getLatestNewsForDay():array {
    $storageQueue = $this->state->get('news_send');
    $cleanTime = strtotime('today 10am');
    $nodeList = [];
    if (empty($storageQueue)) {
      $storageQueue = [];
    }
    foreach ($storageQueue as $value) {
      if ($value['is_sent'] === TRUE || $value['insert_timestamp'] < $cleanTime) {
        array_push($nodeList, $value['nid']);
      }
    }
    return $nodeList;
  }

}
